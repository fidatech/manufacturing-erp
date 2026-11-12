<?php

namespace App\Http\Controllers\Money;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
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

        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'type' => ['nullable', 'in:'.implode(',', self::TYPES)]]);

        $entries = JournalEntry::with(['lines.account.currency', 'lines.account.type', 'creator'])
            ->whereIn('type', self::TYPES)
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('money.index', ['entries' => $entries, 'filters' => $filters, 'types' => self::typeNames()]);
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

        return view('money.form', [
            'kind' => $kind,
            'group' => $group,
            'heading' => $this->heading($kind, $group),
            'moneyAccounts' => Account::money()->active()->with(['currency', 'type'])->orderBy('name')->get(),
            'others' => $this->others($kind, $group),
            'base' => Rates::base()->code,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('money.manage');

        $kind = $this->kind($request);
        $group = $this->group($request);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'money_id' => ['required', 'exists:accounts,id'],
            'other_id' => ['required', 'exists:accounts,id', 'different:money_id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'other_amount' => ['nullable', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [], ['money_id' => __('money account'), 'other_id' => __('account'), 'other_amount' => __('amount in the other currency')]);

        $money = Account::money()->findOrFail($data['money_id']);
        $other = Account::findOrFail($data['other_id']);
        if ($kind === 'transfer' && ! $other->type->is_money) {
            throw new UserError(__('Choose a cash box, bank or sarafi to move the money to.'));
        }
        if ($kind === 'expense' && $other->type->nature !== 'expense') {
            throw new UserError(__('Choose an expense category.'));
        }

        $date = $data['date'];
        $amount = Num::money($data['amount']);
        $base = Rates::toBase($amount, $money->currency_id, $date);
        $otherAmount = $this->otherAmount($money, $other, $amount, $base, $data['other_amount'] ?? null, $date);

        // money comes in: the money account is debited. Money goes out: it is credited.
        $moneySide = $kind === 'in' ? 'debit' : 'credit';
        $otherSide = $kind === 'in' ? 'credit' : 'debit';
        $type = ['in' => 'receipt', 'out' => 'payment', 'expense' => 'expense', 'transfer' => 'transfer'][$kind];

        $description = trim(($data['description'] ?? '') ?: $this->heading($kind, $group).': '.$other->name);

        $entry = Ledger::post($type, $date, $description, [
            ['account' => $money, $moneySide => $amount, 'base' => $base],
            ['account' => $other, $otherSide => $otherAmount, 'base' => $base],
        ]);
        audit('created', $entry, $entry->number.' '.$description);

        return redirect()->to($request->input('return') === 'capital' ? route('capital') : route('money.index'))
            ->with('success', __('Saved as :number.', ['number' => $entry->number]));
    }

    /** Share profit between the shareholders by their percentage. They take it out later with "Money out". */
    public function profitForm(): View
    {
        Gate::authorize('money.manage');

        return view('money.profit', [
            'shareholders' => Account::ofType('shareholder')->active()->with('currency')->orderBy('name')->get(),
            'base' => Rates::base()->code,
        ]);
    }

    public function profitStore(Request $request): RedirectResponse
    {
        Gate::authorize('money.manage');

        $data = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $shareholders = Account::ofType('shareholder')->active()->get()->filter(fn (Account $a) => Num::isPositive($a->metaValue('share_percent', 0)))->values();
        $totalPercent = Num::sum($shareholders->map(fn (Account $a) => $a->metaValue('share_percent', 0)));
        if ($shareholders->isEmpty() || ! Num::isPositive($totalPercent)) {
            throw new UserError(__('First give every shareholder a share (%) on the Shareholders screen.'));
        }

        $amount = Num::money($data['amount']);
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
            $own = $shareholder->currency_id === Rates::base()->id ? $share : Rates::fromBase($share, $shareholder->currency_id, $data['date']);
            $lines[] = ['account' => $shareholder, 'credit' => $own, 'base' => $share];
        }

        $entry = Ledger::post('profit', $data['date'], ($data['description'] ?? null) ?: __('Profit shared between shareholders'), $lines);
        audit('created', $entry, $entry->number.' profit shared');

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

    /** What the amount is worth in the currency of the other account. */
    private function otherAmount(Account $money, Account $other, string $amount, string $base, ?string $given, string $date): string
    {
        if ($other->currency_id === $money->currency_id) {
            return $amount;
        }
        if ($given !== null && $given !== '') {
            return Num::money($given);
        }

        return $other->currency_id === Rates::base()->id ? $base : Rates::fromBase($base, $other->currency_id, $date);
    }
}
