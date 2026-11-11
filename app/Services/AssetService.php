<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\JournalEntry;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

/**
 * Things the company owns and keeps: machines, furniture, licences ...
 * An asset with a "life" loses the same value every month (straight line).
 * For things you can touch this is called depreciation, for licences and
 * software amortization; the system treats both the same way.
 */
class AssetService
{
    /** The account that holds the value of an asset. */
    public static function accountFor(Asset $asset): Account
    {
        if ($asset->class === 'current') {
            return Account::system('current_assets');
        }

        return Account::system($asset->kind === 'intangible' ? 'intangible_assets' : 'fixed_assets');
    }

    public static function create(array $data): Asset
    {
        return DB::transaction(function () use ($data) {
            $date = $data['purchase_date'];
            $cost = Num::money($data['cost']);
            $costBase = Rates::toBase($cost, (int) $data['currency_id'], $date);
            $salvage = Num::money($data['salvage_base'] ?? 0);
            if (Num::cmp($salvage, $costBase) > 0) {
                throw new UserError(__('The value at the end cannot be more than the cost.'));
            }

            $asset = Asset::create([
                'name' => $data['name'],
                'class' => $data['class'],
                'kind' => $data['kind'],
                'purchase_date' => $date,
                'quantity' => Num::money($data['quantity'] ?? 1),
                'cost' => $cost,
                'currency_id' => $data['currency_id'],
                'cost_base' => $costBase,
                'salvage_base' => $salvage,
                'life_months' => (int) ($data['life_months'] ?? 0),
                'paid_from_id' => $data['paid_from_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            // paid from a cash box, bank or supplier; or it was already owned (opening balance)
            $from = ! empty($data['paid_from_id']) ? Account::findOrFail($data['paid_from_id']) : Account::system('opening');
            $fromAmount = $from->currency_id === (int) $data['currency_id']
                ? $cost
                : ($from->currency_id === Rates::base()->id ? $costBase : Rates::fromBase($costBase, $from->currency_id, $date));

            Ledger::post('asset', $date, __('Asset: :name', ['name' => $asset->name]), [
                ['account' => self::accountFor($asset), 'debit' => $costBase],
                ['account' => $from, 'credit' => $fromAmount, 'base' => $costBase],
            ], $asset);

            return $asset;
        });
    }

    /** Value already written off, in the main currency. */
    public static function writtenOff(Asset $asset): string
    {
        return Num::money($asset->depreciations()->sum('amount_base'));
    }

    /**
     * Write off one month for every asset that has a life. Safe to press twice:
     * an asset is never written off twice for the same month.
     *
     * @return array{count: int, total: string}
     */
    public static function runMonth(string $period): array
    {
        return DB::transaction(function () use ($period) {
            $date = date('Y-m-t', strtotime($period.'-01'));
            $rows = [];
            $total = '0';

            $assets = Asset::where('status', 'active')->where('life_months', '>', 0)->whereDate('purchase_date', '<=', $date)->lockForUpdate()->get();
            foreach ($assets as $asset) {
                if ($asset->depreciations()->where('period', $period)->exists()) {
                    continue;
                }
                $limit = Num::sub($asset->cost_base, $asset->salvage_base);
                $left = Num::money(Num::sub($limit, self::writtenOff($asset)));
                if (! Num::isPositive($left)) {
                    continue;
                }
                $monthly = Num::money(Num::div($limit, $asset->life_months));
                $amount = Num::cmp($monthly, $left) > 0 ? $left : $monthly;
                if (! Num::isPositive($amount)) {
                    continue;
                }
                $rows[] = ['asset_id' => $asset->id, 'period' => $period, 'amount_base' => $amount];
                $total = Num::add($total, $amount);
            }

            $total = Num::money($total);
            if (! $rows) {
                return ['count' => 0, 'total' => '0.00'];
            }

            $entry = Ledger::post('depreciation', $date, __('Depreciation and amortization for :period', ['period' => $period]), [
                ['account' => Account::system('depreciation'), 'debit' => $total],
                ['account' => Account::system('accumulated_depreciation'), 'credit' => $total],
            ]);
            foreach ($rows as $row) {
                AssetDepreciation::create($row + ['journal_entry_id' => $entry->id]);
            }

            return ['count' => count($rows), 'total' => $total];
        });
    }

    /** Take back the write-off of one month. */
    public static function undoMonth(string $period): void
    {
        DB::transaction(function () use ($period) {
            $rows = AssetDepreciation::where('period', $period)->get();
            JournalEntry::whereIn('id', $rows->pluck('journal_entry_id')->filter()->unique())->get()->each(fn ($entry) => Ledger::void($entry));
            AssetDepreciation::where('period', $period)->delete();
        });
    }

    /** The asset left the company: sold, broken or given away. */
    public static function dispose(Asset $asset, string $date, mixed $soldFor, ?int $moneyAccountId): void
    {
        DB::transaction(function () use ($asset, $date, $soldFor, $moneyAccountId) {
            if ($asset->status !== 'active') {
                throw new UserError(__('This asset was already removed.'));
            }
            $writtenOff = self::writtenOff($asset);
            $lines = [['account' => self::accountFor($asset), 'credit' => $asset->cost_base]];
            if (Num::isPositive($writtenOff)) {
                $lines[] = ['account' => Account::system('accumulated_depreciation'), 'debit' => $writtenOff];
            }

            $soldBase = '0.00';
            $sold = Num::money($soldFor ?? 0);
            if (Num::isPositive($sold)) {
                if (! $moneyAccountId) {
                    throw new UserError(__('Choose where the money was put.'));
                }
                $money = Account::findOrFail($moneyAccountId);
                $soldBase = Rates::toBase($sold, $money->currency_id, $date);
                $lines[] = ['account' => $money, 'debit' => $sold, 'base' => $soldBase];
            }

            // what is left of its value is a loss; more than that is a gain
            $result = Num::money(Num::sub(Num::add($soldBase, $writtenOff), $asset->cost_base));
            if (Num::isPositive($result)) {
                $lines[] = ['account' => Account::system('other_income'), 'credit' => $result];
            } elseif (Num::isNegative($result)) {
                $lines[] = ['account' => Account::system('depreciation'), 'debit' => Num::abs($result)];
            }

            Ledger::post('asset', $date, __('Asset removed: :name', ['name' => $asset->name]), $lines, $asset);
            $asset->update(['status' => 'disposed']);
        });
    }
}
