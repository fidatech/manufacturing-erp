<?php

namespace App\Http\Controllers\Money;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\Ledger;
use App\Services\Rates;
use App\Support\Num;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Capital: money the shareholders put into the company. One page to add it and to see it. */
class CapitalController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('money.view');

        $shareholders = Account::ofType('shareholder')->with('currency')->orderBy('name')->get();
        $ids = $shareholders->pluck('id');

        // totals in the main currency, by kind of entry
        $sums = DB::table('journal_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_lines.account_id', $ids)->where('journal_lines.is_void', false)
            ->groupBy('journal_entries.type')
            ->selectRaw('journal_entries.type as type, COALESCE(SUM(journal_lines.base_debit),0) as debit, COALESCE(SUM(journal_lines.base_credit),0) as credit')
            ->get()->keyBy('type');
        $credit = fn (string $type) => Num::money($sums->get($type)->credit ?? 0);
        $debit = fn (string $type) => Num::money($sums->get($type)->debit ?? 0);

        $balances = Ledger::balances($ids->all());
        $balance = '0';
        foreach ($ids as $id) {
            $balance = Num::sub($balance, $balances[$id]['base'] ?? 0);   // what the company owes its owners
        }

        return view('money.capital', [
            'base' => Rates::base()->code,
            'cards' => [
                'in' => Num::money(Num::add($credit('receipt'), Num::sub($credit('opening'), $debit('opening')))),
                'out' => $debit('payment'),
                'profit' => $credit('profit'),
                'balance' => Num::money($balance),
            ],
            'shareholders' => $shareholders->where('is_active', true)->values(),
            'balances' => $balances,
            'moneyAccounts' => Account::money()->active()->with(['currency', 'type'])->orderBy('name')->get(),
            'entries' => JournalEntry::with(['lines.account.currency', 'lines.account.type'])
                ->where('type', 'receipt')
                ->whereHas('lines', fn ($q) => $q->whereIn('account_id', $ids))
                ->orderByDesc('date')->orderByDesc('id')->paginate(25),
        ]);
    }
}
