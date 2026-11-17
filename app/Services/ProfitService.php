<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Support\Num;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Profit of the company shared between the shareholders by their share (%).
 * Every month the net profit of the month before is added to their accounts automatically
 * (command profit:distribute, run by the scheduler). A month is never shared twice.
 */
class ProfitService
{
    /** Net profit (income less costs) between two dates, in the main currency. */
    public static function net(string $from, string $to): string
    {
        $sum = JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('account_types', 'account_types.id', '=', 'accounts.account_type_id')
            ->whereIn('account_types.nature', ['income', 'expense'])
            ->where('journal_lines.is_void', false)
            ->whereBetween('journal_lines.date', [$from, $to])
            ->selectRaw('COALESCE(SUM(journal_lines.base_credit), 0) - COALESCE(SUM(journal_lines.base_debit), 0) as net')
            ->value('net');

        return Num::money($sum ?? '0');
    }

    /** The reference that marks a month as shared, e.g. PROFIT-2026-10. */
    public static function reference(string $month): string
    {
        return 'PROFIT-'.$month;
    }

    public static function sharedEntry(string $month): ?JournalEntry
    {
        return JournalEntry::where('type', 'profit')->where('reference', self::reference($month))->whereNull('voided_at')->first();
    }

    /**
     * Share the profit of one month (YYYY-MM). Returns the entry, or a reason why nothing was posted.
     *
     * @return array{0: ?JournalEntry, 1: string}
     */
    public static function distributeMonth(string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month);
        $from = $start->format('Y-m-d');
        $to = $start->endOfMonth()->format('Y-m-d');

        return DB::transaction(function () use ($month, $from, $to) {
            // one month, one sharing: lock the shareholder accounts so two runs cannot both post
            Account::ofType('shareholder')->lockForUpdate()->get();
            if ($entry = self::sharedEntry($month)) {
                return [null, __('The profit of :month was already shared (:number).', ['month' => $month, 'number' => $entry->number])];
            }
            $net = self::net($from, $to);
            if (! Num::isPositive($net)) {
                return [null, __('No profit in :month (:amount). Nothing to share.', ['month' => $month, 'amount' => money($net, Rates::base()->code)])];
            }
            $entry = self::share($net, $to, __('Profit of :month shared between shareholders', ['month' => $month]), self::reference($month));

            return [$entry, __('The profit of :month (:amount) was shared as :number.', ['month' => $month, 'amount' => money($net, Rates::base()->code), 'number' => $entry->number])];
        });
    }

    /** Add an amount (main currency) to the shareholders' accounts by their share. */
    public static function share(string $amount, string $date, string $description, ?string $reference = null): JournalEntry
    {
        $shareholders = Account::ofType('shareholder')->active()->orderBy('id')->get()->filter(fn (Account $a) => Num::isPositive($a->metaValue('share_percent', 0)))->values();
        $totalPercent = Num::sum($shareholders->map(fn (Account $a) => $a->metaValue('share_percent', 0)));
        if ($shareholders->isEmpty() || ! Num::isPositive($totalPercent)) {
            throw new UserError(__('First give every shareholder a share (%) on the Shareholders screen.'));
        }

        $amount = Num::money($amount);
        $left = $amount;
        $lines = [['account' => Account::system('profit_paid'), 'debit' => $amount]];
        foreach ($shareholders as $index => $shareholder) {
            $share = $index === $shareholders->count() - 1
                ? $left
                : Num::money(Num::div(Num::mul($amount, $shareholder->metaValue('share_percent')), $totalPercent));
            $left = Num::money(Num::sub($left, $share));
            if (! Num::isPositive($share)) {
                continue;
            }
            $own = $shareholder->currency_id === Rates::base()->id ? $share : Rates::fromBase($share, $shareholder->currency_id, $date);
            $lines[] = ['account' => $shareholder, 'credit' => $own, 'base' => $share];
        }

        return DB::transaction(function () use ($date, $description, $lines, $reference) {
            $entry = Ledger::post('profit', $date, $description, $lines);
            if ($reference) {
                $entry->update(['reference' => $reference]);
            }
            audit('created', $entry, $entry->number.' profit shared');

            return $entry;
        });
    }
}
