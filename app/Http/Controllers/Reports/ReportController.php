<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalLine;
use App\Services\Ledger;
use App\Services\Rates;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Every report is read from the journal and the stock ledger. All totals are in the main currency. */
class ReportController extends Controller
{
    public function index(): View
    {
        Gate::authorize('reports.view');

        return view('reports.index');
    }

    private function dates(Request $request): array
    {
        $dates = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return [$dates['from'] ?? now()->startOfYear()->toDateString(), $dates['to'] ?? today_iso()];
    }

    /** @return \Illuminate\Support\Collection<int, Account> accounts with ->own and ->base balances */
    private function accounts(?string $from, ?string $to)
    {
        $balances = Ledger::balances(null, $from, $to);

        return Account::with(['type', 'currency'])->orderBy('name')->get()->map(function (Account $account) use ($balances) {
            $account->own = $balances[$account->id]['own'] ?? '0.00';
            $account->base = $balances[$account->id]['base'] ?? '0.00';

            return $account;
        });
    }

    /** Profit and loss: what we earned and what it cost between two dates. */
    public function profit(Request $request): View
    {
        Gate::authorize('reports.view');
        [$from, $to] = $this->dates($request);

        $accounts = $this->accounts($from, $to)->filter(fn ($a) => ! Num::isZero($a->base));
        $income = $accounts->filter(fn ($a) => $a->type->nature === 'income')->map(fn ($a) => ['name' => $a->name, 'amount' => Num::neg($a->base)])->values();
        $cogs = Num::money($accounts->firstWhere('system_key', 'cogs')?->base ?? 0);
        $expenses = $accounts->filter(fn ($a) => $a->type->nature === 'expense' && $a->system_key !== 'cogs')->map(fn ($a) => ['name' => $a->name, 'amount' => $a->base])->values();

        $incomeTotal = Num::money(Num::sum($income->pluck('amount')));
        $expenseTotal = Num::money(Num::sum($expenses->pluck('amount')));
        $gross = Num::money(Num::sub($incomeTotal, $cogs));

        return view('reports.profit', [
            'from' => $from, 'to' => $to, 'base' => Rates::base()->code,
            'income' => $income, 'incomeTotal' => $incomeTotal, 'cogs' => $cogs, 'gross' => $gross,
            'expenses' => $expenses, 'expenseTotal' => $expenseTotal, 'net' => Num::money(Num::sub($gross, $expenseTotal)),
        ]);
    }

    /** Balance sheet: what the company has, what it owes and what belongs to the owners on one day. */
    public function balanceSheet(Request $request): View
    {
        Gate::authorize('reports.view');
        $to = $request->validate(['to' => ['nullable', 'date']])['to'] ?? today_iso();

        $accounts = $this->accounts(null, $to);
        $sections = ['asset' => [], 'liability' => [], 'equity' => []];
        $totals = ['asset' => '0', 'liability' => '0', 'equity' => '0'];
        $profit = '0';

        foreach ($accounts->groupBy(fn ($a) => $a->type->nature) as $nature => $group) {
            if (in_array($nature, ['income', 'expense'], true)) {
                $profit = Num::sub($profit, Num::sum($group->pluck('base')));

                continue;
            }
            foreach ($group->groupBy(fn ($a) => $a->type->name) as $typeName => $members) {
                $sum = Num::sum($members->pluck('base'));
                $amount = Num::money($nature === 'asset' ? $sum : Num::neg($sum));
                if (Num::isZero($amount)) {
                    continue;
                }
                $sections[$nature][] = ['name' => $typeName, 'amount' => $amount, 'members' => $members->filter(fn ($a) => ! Num::isZero($a->base))->map(fn ($a) => ['name' => $a->name, 'amount' => Num::money($nature === 'asset' ? $a->base : Num::neg($a->base))])->values()];
                $totals[$nature] = Num::add($totals[$nature], $amount);
            }
        }
        $profit = Num::money($profit);
        $totals['equity'] = Num::add($totals['equity'], $profit);

        return view('reports.balance', [
            'to' => $to, 'base' => Rates::base()->code, 'sections' => $sections, 'profit' => $profit,
            'totals' => array_map(fn ($t) => Num::money($t), $totals),
            'balanced' => Num::isZero(Num::money(Num::sub($totals['asset'], Num::add($totals['liability'], $totals['equity'])))),
        ]);
    }

    /** Trial balance: every account with its debit or credit balance. Both columns must be equal. */
    public function trial(Request $request): View
    {
        Gate::authorize('reports.view');
        $to = $request->validate(['to' => ['nullable', 'date']])['to'] ?? today_iso();

        $rows = $this->accounts(null, $to)->filter(fn ($a) => ! Num::isZero($a->base))->sortBy(fn ($a) => $a->type->sort.$a->name)->values();
        $debit = Num::money(Num::sum($rows->filter(fn ($a) => Num::isPositive($a->base))->pluck('base')));
        $credit = Num::money(Num::neg(Num::sum($rows->filter(fn ($a) => Num::isNegative($a->base))->pluck('base'))));

        return view('reports.trial', ['to' => $to, 'base' => Rates::base()->code, 'rows' => $rows, 'debit' => $debit, 'credit' => $credit]);
    }

    /** Cash flow: money at the start, money in, money out and money at the end, for every cash box, bank and sarafi. */
    public function cashFlow(Request $request): View
    {
        Gate::authorize('reports.view');
        [$from, $to] = $this->dates($request);

        $rows = [];
        foreach (Account::money()->with('currency')->orderBy('name')->get() as $account) {
            $moved = JournalLine::where('account_id', $account->id)->where('is_void', false)->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
                ->selectRaw('COALESCE(SUM(debit),0) as money_in, COALESCE(SUM(credit),0) as money_out')->toBase()->first();
            $opening = Ledger::balance($account->id, $from, before: true);
            $rows[] = ['account' => $account, 'opening' => $opening, 'in' => Num::money($moved->money_in), 'out' => Num::money($moved->money_out), 'closing' => Ledger::balance($account->id, $to)];
        }

        $moneyIds = Account::money()->pluck('id');
        $byType = DB::table('journal_lines')->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_lines.account_id', $moneyIds)->where('journal_lines.is_void', false)
            ->whereDate('journal_lines.date', '>=', $from)->whereDate('journal_lines.date', '<=', $to)
            ->groupBy('journal_entries.type')
            ->selectRaw('journal_entries.type as type, COALESCE(SUM(journal_lines.base_debit),0) as money_in, COALESCE(SUM(journal_lines.base_credit),0) as money_out')
            ->get();

        return view('reports.cash', ['from' => $from, 'to' => $to, 'base' => Rates::base()->code, 'rows' => $rows, 'byType' => $byType, 'typeNames' => self::entryTypes()]);
    }

    /** The parts of the system and the kinds of entries each one makes. */
    public const MODULES = [
        'money' => ['Money', ['receipt', 'payment', 'expense', 'transfer', 'exchange', 'dead_cash', 'profit']],
        'sales' => ['Sales', ['sale', 'sale_return']],
        'purchases' => ['Purchases', ['purchase', 'purchase_return']],
        'production' => ['Manufacturing', ['production']],
        'inventory' => ['Inventory', ['stock_count']],
        'hr' => ['Human Resources', ['payroll']],
        'assets' => ['Assets', ['asset', 'depreciation']],
        'accounting' => ['Accounting', ['journal', 'opening']],
    ];

    public static function moduleOf(string $type): string
    {
        foreach (self::MODULES as $key => [, $types]) {
            if (in_array($type, $types, true)) {
                return $key;
            }
        }

        return 'accounting';
    }

    /** Enterprise transactions: every line of every account, by module, with filters. */
    public function transactions(Request $request): View
    {
        Gate::authorize('reports.view');
        [$from, $to] = $this->dates($request);
        $filters = $request->validate([
            'module' => ['nullable', 'in:'.implode(',', array_keys(self::MODULES))],
            'account' => ['nullable', 'integer'],
            'currency' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_lines.is_void', false)
            ->whereBetween('journal_lines.date', [$from, $to])
            ->when($filters['module'] ?? null, fn ($q, $module) => $q->whereIn('journal_entries.type', self::MODULES[$module][1]))
            ->when($filters['account'] ?? null, fn ($q, $id) => $q->where('journal_lines.account_id', $id))
            ->when($filters['currency'] ?? null, fn ($q, $id) => $q->where('accounts.currency_id', $id))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('journal_entries.number', 'like', "%$term%")->orWhere('journal_entries.description', 'like', "%$term%")->orWhere('journal_entries.reference', 'like', "%$term%")));

        // totals of each module, in the main currency
        $summary = (clone $query)->groupBy('journal_entries.type')
            ->selectRaw('journal_entries.type as type, COUNT(DISTINCT journal_entries.id) as entries, COALESCE(SUM(journal_lines.base_debit), 0) as debit, COALESCE(SUM(journal_lines.base_credit), 0) as credit')
            ->toBase()->get()
            ->groupBy(fn ($row) => self::moduleOf($row->type))
            ->map(fn ($rows) => ['entries' => $rows->sum('entries'), 'debit' => Num::money(Num::sum($rows->pluck('debit'))), 'credit' => Num::money(Num::sum($rows->pluck('credit')))]);
        $modules = collect(self::MODULES)->filter(fn ($m, $key) => $summary->has($key))->map(fn ($m, $key) => ['name' => __($m[0])] + $summary[$key]);

        $lines = $query->select('journal_lines.*', 'journal_entries.number as entry_number', 'journal_entries.type as entry_type', 'journal_entries.description as entry_description', 'journal_entries.reference as entry_reference')
            ->with(['account.currency'])
            ->orderBy('journal_lines.date')->orderBy('journal_lines.journal_entry_id')->orderBy('journal_lines.id')
            ->paginate(per_page(100))->withQueryString();

        return view('reports.transactions', [
            'from' => $from, 'to' => $to, 'base' => Rates::base()->code, 'filters' => $filters,
            'modules' => $modules, 'lines' => $lines,
            'totals' => ['debit' => Num::money(Num::sum($modules->pluck('debit'))), 'credit' => Num::money(Num::sum($modules->pluck('credit'))), 'entries' => $modules->sum('entries')],
            'moduleNames' => collect(self::MODULES)->map(fn ($m) => __($m[0]))->all(),
            'accounts' => Account::with('currency')->orderBy('code')->get(['id', 'code', 'name', 'currency_id']),
            'currencies' => \App\Models\Currency::orderBy('id')->get(),
        ]);
    }

    public static function entryTypes(): array
    {
        return [
            'receipt' => __('Money received'), 'payment' => __('Money paid'), 'expense' => __('Expenses'), 'transfer' => __('Transfers and exchange'),
            'purchase' => __('Costs of purchases'), 'asset' => __('Assets'), 'opening' => __('Opening balances'), 'journal' => __('Journal entries'),
            'profit' => __('Profit shared'), 'payroll' => __('Salaries'),
        ];
    }

    /** Who owes us and whom we owe. */
    public function balances(Request $request): View
    {
        Gate::authorize('reports.view');
        $to = $request->validate(['to' => ['nullable', 'date']])['to'] ?? today_iso();

        $parties = $this->accounts(null, $to)->filter(fn ($a) => $a->type->is_party && ! Num::isZero($a->own));

        return view('reports.balances', [
            'to' => $to, 'base' => Rates::base()->code,
            'owesUs' => $parties->filter(fn ($a) => Num::isPositive($a->own))->sortByDesc(fn ($a) => (float) $a->base)->values(),
            'weOwe' => $parties->filter(fn ($a) => Num::isNegative($a->own))->sortBy(fn ($a) => (float) $a->base)->values(),
        ]);
    }

    public const PARTY_REPORTS = [
        'customers' => ['Customers Report', 'customer'],
        'suppliers' => ['Suppliers Report', 'supplier'],
        'shareholders' => ['Shareholder Statements', 'shareholder'],
        'debtors' => ['Debtors', null],
        'creditors' => ['Creditors', null],
    ];

    /** A list of people with their balance and a link to the statement. */
    public function parties(Request $request): View
    {
        $group = $request->route('group');
        abort_unless(isset(self::PARTY_REPORTS[$group]), 404);
        Gate::authorize('reports.view');
        $to = $request->validate(['to' => ['nullable', 'date']])['to'] ?? today_iso();
        [$title, $type] = self::PARTY_REPORTS[$group];

        $rows = $this->accounts(null, $to)->filter(function ($account) use ($group, $type) {
            if ($type) {
                return $account->type->key === $type;
            }
            // debtors owe us; creditors are owed by us
            return $account->type->is_party && ! $account->type->is_money
                && ($group === 'debtors' ? Num::isPositive($account->own) : Num::isNegative($account->own));
        })->sortByDesc(fn ($account) => abs((float) $account->base))->values();

        return view('reports.parties', [
            'title' => __($title), 'to' => $to, 'base' => Rates::base()->code, 'rows' => $rows,
            'total' => Num::money(Num::sum($rows->pluck('base'))),
        ]);
    }

    /** Sales by product and by customer, with cost and profit. Returns are taken off. */
    public function sales(Request $request): View
    {
        Gate::authorize('reports.view');
        [$from, $to] = $this->dates($request);

        $sign = "CASE WHEN sales.type = 'return' THEN -1 ELSE 1 END";
        $lines = fn () => DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->whereIn('sales.type', ['invoice', 'return'])->where('sales.status', 'posted')
            ->whereDate('sales.date', '>=', $from)->whereDate('sales.date', '<=', $to);

        $byItem = $lines()->join('items', 'items.id', '=', 'sale_lines.item_id')->groupBy('items.id', 'items.name', 'items.code')
            ->selectRaw("items.name as name, items.code as code, SUM(sale_lines.qty * $sign) as qty, SUM(sale_lines.total * 1.0 / sales.rate * $sign) as amount, SUM(sale_lines.cost_base * $sign) as cost")
            ->orderByDesc('amount')->get();
        $byCustomer = $lines()->join('accounts', 'accounts.id', '=', 'sales.customer_id')->groupBy('accounts.id', 'accounts.name')
            ->selectRaw("accounts.name as name, SUM(sale_lines.total * 1.0 / sales.rate * $sign) as amount, SUM(sale_lines.cost_base * $sign) as cost")
            ->orderByDesc('amount')->get();

        return view('reports.sales', ['from' => $from, 'to' => $to, 'base' => Rates::base()->code, 'byItem' => $byItem, 'byCustomer' => $byCustomer]);
    }

    /** Purchases by item and by supplier. Returns are taken off. */
    public function purchases(Request $request): View
    {
        Gate::authorize('reports.view');
        [$from, $to] = $this->dates($request);

        $sign = "CASE WHEN purchases.type = 'return' THEN -1 ELSE 1 END";
        $lines = fn () => DB::table('purchase_lines')->join('purchases', 'purchases.id', '=', 'purchase_lines.purchase_id')
            ->whereIn('purchases.type', ['invoice', 'return'])->where('purchases.status', 'posted')
            ->whereDate('purchases.date', '>=', $from)->whereDate('purchases.date', '<=', $to);

        $byItem = $lines()->join('items', 'items.id', '=', 'purchase_lines.item_id')->groupBy('items.id', 'items.name', 'items.code')
            ->selectRaw("items.name as name, items.code as code, SUM(purchase_lines.qty * $sign) as qty, SUM(purchase_lines.total * 1.0 / purchases.rate * $sign) as amount")
            ->orderByDesc('amount')->get();
        $bySupplier = $lines()->join('accounts', 'accounts.id', '=', 'purchases.supplier_id')->groupBy('accounts.id', 'accounts.name')
            ->selectRaw("accounts.name as name, SUM(purchase_lines.total * 1.0 / purchases.rate * $sign) as amount")
            ->orderByDesc('amount')->get();

        return view('reports.purchases', ['from' => $from, 'to' => $to, 'base' => Rates::base()->code, 'byItem' => $byItem, 'bySupplier' => $bySupplier]);
    }
}
