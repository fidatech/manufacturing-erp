<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountType;
use App\Models\JournalLine;
use App\Services\Ledger;
use App\Services\Rates;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ChartController extends Controller
{
    /** Chart of accounts: every account, grouped by kind, with its balance. */
    public function index(): View
    {
        Gate::authorize('accounts.view');

        $balances = Ledger::balances();
        $types = AccountType::with(['accounts' => fn ($q) => $q->with('currency')->orderBy('name')])->orderBy('sort')->get();

        $groups = [];
        foreach (['asset', 'liability', 'equity', 'income', 'expense'] as $nature) {
            $groups[$nature] = $types->where('nature', $nature)->values();
        }

        return view('accounts.chart', [
            'groups' => $groups,
            'balances' => $balances,
            'base' => Rates::base()->code,
            'natures' => [
                'asset' => __('What we have and who owes us'),
                'liability' => __('Who we owe'),
                'equity' => __('Owners\' money'),
                'income' => __('Income'),
                'expense' => __('Expenses'),
            ],
        ]);
    }

    /** Statement of one account: every movement between two dates with a running balance. */
    public function statement(Request $request): View
    {
        $account = Account::with(['currency', 'type'])->findOrFail($request->route('id'));
        Gate::authorize($account->type->key === 'employee' ? 'hr.view' : 'accounts.view');

        $dates = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $from = $dates['from'] ?? now()->startOfYear()->toDateString();
        $to = $dates['to'] ?? today_iso();

        $opening = Ledger::balance($account->id, $from, before: true);
        $lines = JournalLine::with('entry')
            ->where('account_id', $account->id)
            ->where('is_void', false)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $running = $opening;
        $rows = [];
        $debit = '0';
        $credit = '0';
        foreach ($lines as $line) {
            $running = Num::money(Num::add($running, Num::sub($line->debit, $line->credit)));
            $debit = Num::add($debit, $line->debit);
            $credit = Num::add($credit, $line->credit);
            $rows[] = ['line' => $line, 'balance' => $running];
        }

        return view('accounts.statement', [
            'account' => $account,
            'from' => $from,
            'to' => $to,
            'opening' => $opening,
            'rows' => $rows,
            'debit' => $debit,
            'credit' => $credit,
            'closing' => $running,
            'money' => $account->type->is_money,
        ]);
    }
}
