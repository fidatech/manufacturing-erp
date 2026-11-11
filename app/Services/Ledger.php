<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Support\Num;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The one journal behind every screen.
 *
 * Every line is kept twice: in the currency of its account (debit / credit) and in
 * the main currency (base_debit / base_credit). An entry always balances in the main
 * currency. When accounts of different currencies meet, the small rounding or rate
 * difference goes to the "Exchange rate difference" account by itself.
 */
class Ledger
{
    /**
     * @param  array<int, array{account: Account|int, debit?: mixed, credit?: mixed, base?: mixed, memo?: ?string}>  $lines
     */
    public static function post(string $type, string $date, ?string $description, array $lines, ?Model $source = null): JournalEntry
    {
        return DB::transaction(function () use ($type, $date, $description, $lines, $source) {
            $baseId = Rates::base()->id;
            $rows = [];
            $allBase = true;
            $debits = '0';
            $credits = '0';

            foreach ($lines as $line) {
                $account = $line['account'] instanceof Account ? $line['account'] : Account::findOrFail($line['account']);
                $debit = Num::money($line['debit'] ?? 0);
                $credit = Num::money($line['credit'] ?? 0);

                if (Num::isNegative($debit) || Num::isNegative($credit)) {
                    throw new UserError(__('app.errors.negative_amount'));
                }
                $amount = Num::isPositive($debit) ? $debit : $credit;
                $base = isset($line['base'])
                    ? Num::money($line['base'])
                    : Rates::toBase($amount, $account->currency_id, $date);
                // a line can exist in the main currency only (value without money of its own)
                if (Num::isZero($amount) && Num::isZero($base)) {
                    continue;
                }
                $isDebit = Num::isPositive($debit) || (Num::isZero($credit) && ($line['side'] ?? 'debit') === 'debit');
                if ($account->currency_id !== $baseId) {
                    $allBase = false;
                }

                $rows[] = [
                    'account_id' => $account->id,
                    'date' => $date,
                    'debit' => $isDebit ? $amount : '0',
                    'credit' => $isDebit ? '0' : $amount,
                    'base_debit' => $isDebit ? $base : '0',
                    'base_credit' => $isDebit ? '0' : $base,
                    'memo' => isset($line['memo']) ? mb_substr((string) $line['memo'], 0, 500) : null,
                ];
                $debits = Num::add($debits, $isDebit ? $base : '0');
                $credits = Num::add($credits, $isDebit ? '0' : $base);
            }

            if (count($rows) < 2) {
                throw new UserError(__('app.errors.empty_entry'));
            }

            $difference = Num::money(Num::sub($debits, $credits));
            if (! Num::isZero($difference)) {
                if ($allBase) {
                    throw new UserError(__('app.errors.unbalanced'));
                }
                $gain = Num::isPositive($difference);
                $rows[] = [
                    'account_id' => Account::system('exchange_diff')->id,
                    'date' => $date,
                    'debit' => $gain ? '0' : Num::abs($difference),
                    'credit' => $gain ? $difference : '0',
                    'base_debit' => $gain ? '0' : Num::abs($difference),
                    'base_credit' => $gain ? $difference : '0',
                    'memo' => null,
                ];
            }

            $entry = JournalEntry::create([
                'number' => Sequence::next('JV'),
                'date' => $date,
                'type' => $type,
                'description' => $description ? mb_substr($description, 0, 500) : null,
                'source_type' => $source ? $source->getMorphClass() : null,
                'source_id' => $source?->getKey(),
                'created_by' => auth()->id(),
            ]);

            foreach ($rows as $row) {
                JournalLine::create($row + ['journal_entry_id' => $entry->id]);
            }

            return $entry;
        });
    }

    /** Cancel an entry: it stays visible in the day book but no longer counts. */
    public static function void(JournalEntry $entry): void
    {
        if ($entry->isVoid()) {
            return;
        }

        DB::transaction(function () use ($entry) {
            $entry->update(['voided_at' => now(), 'voided_by' => auth()->id()]);
            $entry->lines()->update(['is_void' => true]);
            AuditLog::record('cancelled', $entry, $entry->number.' '.$entry->description);
        });
    }

    /** Cancel every entry that a paper (sale, purchase, payroll ...) created. */
    public static function voidSource(Model $source): void
    {
        JournalEntry::where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->whereNull('voided_at')
            ->get()
            ->each(fn (JournalEntry $entry) => self::void($entry));
    }

    /** Balance in the account's own currency: debits minus credits. */
    public static function balance(int $accountId, ?string $upTo = null, bool $before = false): string
    {
        $row = self::sums($accountId, $upTo, $before);

        return Num::money(Num::sub($row->debit ?? 0, $row->credit ?? 0));
    }

    /** Balance in the main currency. */
    public static function baseBalance(int $accountId, ?string $upTo = null): string
    {
        $row = self::sums($accountId, $upTo, false);

        return Num::money(Num::sub($row->base_debit ?? 0, $row->base_credit ?? 0));
    }

    private static function sums(int $accountId, ?string $upTo, bool $before): object
    {
        return JournalLine::where('account_id', $accountId)
            ->where('is_void', false)
            ->when($upTo, fn ($q) => $q->whereDate('date', $before ? '<' : '<=', $upTo))
            ->selectRaw('COALESCE(SUM(debit),0) as debit, COALESCE(SUM(credit),0) as credit, COALESCE(SUM(base_debit),0) as base_debit, COALESCE(SUM(base_credit),0) as base_credit')
            ->toBase()
            ->first();
    }

    /**
     * Balances of many accounts at once: [account id => ['own' => .., 'base' => ..]].
     *
     * @param  array<int>|null  $accountIds
     */
    public static function balances(?array $accountIds = null, ?string $from = null, ?string $to = null): array
    {
        $rows = JournalLine::where('is_void', false)
            ->when($accountIds !== null, fn ($q) => $q->whereIn('account_id', $accountIds))
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->groupBy('account_id')
            ->selectRaw('account_id, COALESCE(SUM(debit),0) as debit, COALESCE(SUM(credit),0) as credit, COALESCE(SUM(base_debit),0) as base_debit, COALESCE(SUM(base_credit),0) as base_credit')
            ->toBase()
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->account_id] = [
                'own' => Num::money(Num::sub($row->debit, $row->credit)),
                'base' => Num::money(Num::sub($row->base_debit, $row->base_credit)),
            ];
        }

        return $out;
    }
}
