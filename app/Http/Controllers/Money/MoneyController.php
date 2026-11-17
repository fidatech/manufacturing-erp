<?php

namespace App\Http\Controllers\Money;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\Ledger;
use App\Services\Rates;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Everyday money: received, paid, expenses, transfers and exchange.
 * The user only says who, how much and where the money is; the journal entry is made here.
 *
 * "group" narrows the list of people to choose from and names the screen:
 *   /money/in?group=shareholder   capital put in by a shareholder
 *   /money/out?group=shareholder  money taken out by a shareholder
 *   /money/in?group=liability     loan received      /money/out?group=liability  loan paid back
 *   /money/out?group=supplier     payment or advance to a supplier
 *   /money/out?group=employee     salary or advance paid
 */
class MoneyController extends Controller
{
    public const KINDS = ['in', 'out', 'expense', 'transfer'];

    public const TYPES = ['receipt', 'payment', 'expense', 'transfer', 'profit'];

    /** group => [kind => [title, entry type]] */
    private const GROUPS = [
        'customer' => ['in' => 'Money received from a customer', 'out' => 'Money returned to a customer'],
        'supplier' => ['in' => 'Money returned by a supplier', 'out' => 'Payment or advance to a supplier'],
        'shareholder' => ['in' => 'Capital put in by a shareholder', 'out' => 'Money taken out by a shareholder'],
        'liability' => ['in' => 'Loan received', 'out' => 'Loan paid back'],
        'employee' => ['in' => 'Money returned by an employee', 'out' => 'Salary or advance paid to an employee'],
        'warehouse_manager' => ['in' => 'Money returned by a warehouse manager', 'out' => 'Money given to a warehouse manager'],
        'exchange' => ['transfer' => 'Currency exchange'],
        'transfer' => ['transfer' => 'Transfer between accounts'],
    ];

    public function index(Request $request): View
    {
        Gate::authorize('money.view');

        $filters = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'type' => ['nullable', 'in:'.implode(',', self::TYPES)],
            'category' => ['nullable', 'integer'], 'currency' => ['nullable', 'integer'],
        ]);
        $category = (int) ($filters['category'] ?? 0);
        $currency = (int) ($filters['currency'] ?? 0);

        $query = JournalEntry::query()
            ->whereIn('type', self::TYPES)
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date))
            // an expense category, or any account of the entry
            ->when($category > 0, fn ($q) => $q->whereHas('lines', fn ($l) => $l->where('account_id', $category)))
            // the currency the money moved in
            ->when($currency > 0, fn ($q) => $q->whereHas('lines', fn ($l) => $l->whereHas('account', fn ($a) => $a->where('currency_id', $currency))));

        // expenses: what the chosen ones cost in the main currency (cancelled ones do not count)
        $spent = null;
        if (($filters['type'] ?? null) === 'expense') {
            $spent = Num::money(JournalLine::where('is_void', false)->whereIn('journal_entry_id', (clone $query)->whereNull('voided_at')->select('id'))
                ->whereHas('account.type', fn ($t) => $t->where('nature', 'expense'))
                ->when($category > 0, fn ($l) => $l->where('account_id', $category))
                ->selectRaw('COALESCE(SUM(base_debit), 0) - COALESCE(SUM(base_credit), 0) as total')->value('total') ?? '0');
        }

        $entries = $query->with(['lines.account.currency', 'lines.account.type', 'creator'])
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(per_page(25))->withQueryString();

        return view('money.index', [
            'entries' => $entries, 'filters' => $filters, 'types' => self::typeNames(), 'spent' => $spent,
            'base' => Rates::base()->code,
            'categories' => Account::ofType('expense')->orderBy('name')->get(['id', 'name']),
            'currencies' => \App\Models\Currency::orderBy('code')->pluck('code', 'id')->all(),
        ]);
    }

    public static function typeNames(): array
    {
        return [
            'receipt' => __('Money in'), 'payment' => __('Money out'), 'expense' => __('Expense'),
            'transfer' => __('Transfer / exchange'), 'profit' => __('Profit shared'),
        ];
    }

    public function create(Request $request): View
    {
        Gate::authorize('money.manage');

        $kind = $this->kind($request);
        $group = $this->group($request);

        $moneyAccounts = Account::money()->active()->with(['currency', 'type'])->orderBy('name')->get();
        $others = $this->others($kind, $group);

        return view('money.form', [
            'kind' => $kind,
            'group' => $group,
            'quickOther' => $this->quickOther($kind, $group),
            'heading' => $this->heading($kind, $group),
            'moneyAccounts' => $moneyAccounts,
            'others' => $others,
            // balance of every account on the screen, shown while choosing
            'balances' => Ledger::balances($moneyAccounts->pluck('id')->merge($others->pluck('id'))->all()),
            'base' => Rates::base()->code,
            'rates' => \App\Services\ExchangeService::ratesOn(old('date') ?: today_iso()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('money.manage');

        $kind = $this->kind($request);
        $group = $this->group($request);

        // Money in and money out can take several cash boxes, banks or sarafi in one go, each in its own
        // currency with its own rate (lines[]). The other screens send one money account (money_id).
        if (! $request->has('lines')) {
            $request->merge(['lines' => [[
                'money_id' => $request->input('money_id'), 'amount' => $request->input('amount'), 'other_amount' => $request->input('other_amount'),
            ]]]);
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'other_id' => ['required', 'exists:accounts,id'],
            // the one-line screens send these: their mistakes are shown on their own boxes
            'money_id' => ['sometimes', 'nullable', 'exists:accounts,id'],
            'amount' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'lines' => ['required', 'array'],
            'lines.*.money_id' => ['nullable', 'exists:accounts,id'],
            'lines.*.amount' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.rate' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.operation' => ['nullable', 'in:multiply,divide'],
            'lines.*.other_amount' => ['nullable', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
        ], [], [
            'other_id' => __('account'), 'lines.*.money_id' => __('money account'), 'lines.*.amount' => __('amount'),
            'lines.*.other_amount' => __('amount in the other currency'), 'lines.*.rate' => __('exchange rate'),
        ]);

        $other = Account::findOrFail($data['other_id']);
        if ($kind === 'transfer' && ! $other->type->is_money) {
            throw new UserError(__('Choose a cash box, bank or sarafi to move the money to.'));
        }
        if ($kind === 'expense' && $other->type->nature !== 'expense') {
            throw new UserError(__('Choose an expense category.'));
        }

        $date = $data['date'];
        // money comes in: the money account is debited. Money goes out: it is credited.
        $moneySide = $kind === 'in' ? 'debit' : 'credit';
        $otherSide = $kind === 'in' ? 'credit' : 'debit';
        $type = ['in' => 'receipt', 'out' => 'payment', 'expense' => 'expense', 'transfer' => 'transfer'][$kind];

        $lines = [];
        $otherTotal = '0';
        $baseTotal = '0';
        $parts = [];
        foreach ($data['lines'] as $line) {
            if (empty($line['money_id']) && empty($line['amount'])) {
                continue;
            }
            if (empty($line['money_id']) || empty($line['amount'])) {
                throw new UserError(__('Every line needs a money account and an amount.'));
            }
            $money = Account::money()->findOrFail($line['money_id']);
            if ($money->id === $other->id) {
                throw new UserError(__('The money cannot go to the same account it comes from.'));
            }
            $amount = Num::money($line['amount']);
            $base = Rates::toBase($amount, $money->currency_id, $date);
            $otherAmount = $this->otherAmount($money, $other, $amount, $base, $line, $date);

            $lines[] = ['account' => $money, $moneySide => $amount, 'base' => $base];
            $otherTotal = Num::add($otherTotal, $otherAmount);
            $baseTotal = Num::add($baseTotal, $base);
            $parts[] = money($amount, $money->currency->code);
        }
        if (! $lines) {
            throw new UserError(__('Write at least one amount.'));
        }
        $lines[] = ['account' => $other, $otherSide => Num::money($otherTotal), 'base' => Num::money($baseTotal)];

        $description = trim(($data['description'] ?? '') ?: $this->heading($kind, $group).': '.$other->name.(count($parts) > 1 ? ' ('.implode(' + ', $parts).')' : ''));

        $entry = DB::transaction(function () use ($type, $date, $description, $lines, $data) {
            $entry = Ledger::post($type, $date, $description, $lines);
            if (! empty($data['reference'])) {
                $entry->update(['reference' => trim($data['reference'])]);
            }

            return $entry;
        });
        audit('created', $entry, $entry->number.' '.$description);

        return redirect()->to($request->input('return') === 'capital' ? route('capital') : route('money.index'))
            ->with('success', __('Saved as :number.', ['number' => $entry->number]));
    }

    /**
     * The receipt of one money entry, ready for the printer. A payment to a supplier with a TT number
     * is a "TT receipt"; money received is a receipt, money paid a payment voucher.
     */
    public function receipt(Request $request): View
    {
        Gate::authorize('money.view');

        $entry = JournalEntry::with(['lines.account.currency', 'lines.account.type', 'creator'])->whereIn('type', self::TYPES)->findOrFail($request->route('id'));
        $people = $entry->lines->filter(fn ($line) => ! $line->account->type->is_money || $entry->type === 'transfer');
        $person = $entry->type === 'transfer' ? $entry->lines->first(fn ($line) => Num::isPositive($line->debit))?->account : $people->first()?->account;
        $moneyLines = $entry->lines->filter(fn ($line) => $line->account->type->is_money && $line->account->id !== $person?->id);
        $isTt = $entry->type === 'payment' && $person?->type->key === 'supplier' && $entry->reference;

        return view('money.receipt', [
            'entry' => $entry,
            'person' => $person,
            'personLine' => $entry->lines->first(fn ($line) => $line->account->id === $person?->id),
            'moneyLines' => $moneyLines,
            'title' => $isTt ? __('TT Receipt') : ($entry->type === 'receipt' ? __('Money Receipt') : __('Payment Voucher')),
            'isTt' => $isTt,
            'logo' => \App\Http\Controllers\Setup\CompanyController::logoDataUri(),
        ]);
    }

    /** Share profit between the shareholders by their percentage. They take it out later with "Money out". */
    public function profitForm(): View
    {
        Gate::authorize('money.manage');

        // the last six months that have ended: their profit and whether it was shared
        $months = collect(range(1, 6))->map(function (int $back) {
            $start = now()->startOfMonth()->subMonthsNoOverflow($back);
            $month = $start->format('Y-m');

            return ['month' => $month, 'net' => \App\Services\ProfitService::net($start->format('Y-m-d'), $start->copy()->endOfMonth()->format('Y-m-d')), 'entry' => \App\Services\ProfitService::sharedEntry($month)];
        });

        return view('money.profit', [
            'shareholders' => Account::ofType('shareholder')->active()->with('currency')->orderBy('name')->get(),
            'base' => Rates::base()->code,
            'months' => $months,
        ]);
    }

    /** Share one month's profit now, instead of waiting for the 1st of the month. */
    public function profitMonth(Request $request): RedirectResponse
    {
        Gate::authorize('money.manage');

        $month = $request->validate(['month' => ['required', 'date_format:Y-m', 'before:'.now()->startOfMonth()->format('Y-m-d')]])['month'];
        [$entry, $message] = \App\Services\ProfitService::distributeMonth($month);

        return back()->with($entry ? 'success' : 'warning', $message);
    }

    public function profitStore(Request $request): RedirectResponse
    {
        Gate::authorize('money.manage');

        $data = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $entry = \App\Services\ProfitService::share($data['amount'], $data['date'], ($data['description'] ?? null) ?: __('Profit shared between shareholders'));

        return redirect()->route('money.index')->with('success', __('Saved as :number.', ['number' => $entry->number]));
    }

    /** Cancel a money entry made on these screens. Entries made by a sale or purchase are cancelled there. */
    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('money.manage');

        $entry = JournalEntry::whereIn('type', self::TYPES)->whereNull('source_type')->findOrFail($request->route('id'));
        DB::transaction(fn () => Ledger::void($entry));

        return back()->with('success', __(':number was cancelled.', ['number' => $entry->number]));
    }

    /** The "add new" pop-up beside the list of people, when the screen is about one kind of account. */
    private function quickOther(string $kind, ?string $group): \Illuminate\Support\HtmlString|string
    {
        $titles = [
            'customer' => __('New customer'), 'supplier' => __('New supplier'), 'shareholder' => __('New shareholder'),
            'liability' => __('New loan account'), 'employee' => __('New employee'), 'warehouse_manager' => __('New warehouse manager'),
        ];
        if ($kind === 'expense') {
            return quick_add('accounts', __('New expense category'), 'accounts.create', ['type' => 'expense']);
        }

        return isset($titles[$group]) ? quick_add('accounts', $titles[$group], 'accounts.create', ['type' => $group]) : '';
    }

    private function kind(Request $request): string
    {
        $kind = $request->route('kind');
        abort_unless(in_array($kind, self::KINDS, true), 404);

        return $kind;
    }

    private function group(Request $request): ?string
    {
        $group = $request->input('group');

        return isset(self::GROUPS[$group]) ? $group : null;
    }

    private function heading(string $kind, ?string $group): string
    {
        if ($group && isset(self::GROUPS[$group][$kind])) {
            return __(self::GROUPS[$group][$kind]);
        }

        return ['in' => __('Money in'), 'out' => __('Money out'), 'expense' => __('Expense'), 'transfer' => __('Transfer / exchange')][$kind];
    }

    /** The accounts that can stand on the other side of the money. */
    private function others(string $kind, ?string $group)
    {
        $query = Account::active()->with(['currency', 'type'])->whereNull('system_key')->orderBy('name');

        if ($kind === 'transfer') {
            return $query->money()->get();
        }
        if ($kind === 'expense') {
            return $query->whereHas('type', fn ($q) => $q->where('nature', 'expense'))->get();
        }
        if ($group && ! in_array($group, ['exchange', 'transfer'], true)) {
            return $query->ofType($group)->get();
        }

        return $query->whereHas('type', fn ($q) => $q->where('is_money', false)->whereNotIn('nature', $kind === 'in' ? ['expense'] : ['income']))->get();
    }

    /**
     * What the amount is worth in the currency of the other account: the amount written by hand,
     * else the line's own rate (multiply or divide), else the rate of the day.
     */
    private function otherAmount(Account $money, Account $other, string $amount, string $base, array $line, string $date): string
    {
        if ($other->currency_id === $money->currency_id) {
            return $amount;
        }
        if (! empty($line['other_amount'])) {
            return Num::money($line['other_amount']);
        }
        if (! empty($line['rate']) && ! empty($line['operation'])) {
            return \App\Services\ExchangeService::convert($amount, $line['rate'], $line['operation']);
        }

        return $other->currency_id === Rates::base()->id ? $base : Rates::fromBase($base, $other->currency_id, $date);
    }
}
