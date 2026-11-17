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
 * Dead cash: customers who owe us and have not paid for a long time.
 * The list shows how much each one owes, when he last paid and how many days ago.
 * Money that will never come back can be written off: it leaves the customer's account
 * and goes to the expense "Dead cash" (and can be cancelled).
 */
class DeadCashController extends Controller
{
    public const DAYS = [30, 60, 90, 180, 365];

    public function index(Request $request): View
    {
        Gate::authorize('money.view');

        $filters = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'currency' => ['nullable', 'integer'],
            'category' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'account' => ['nullable', 'integer'],
        ]);
        $days = (int) ($filters['days'] ?? 90);
        $today = \Carbon\CarbonImmutable::parse(today_iso());

        // every customer who owes us, with the day he last paid (or, if he never paid, the day he first owed)
        $customers = Account::ofType('customer')->with('currency')
            ->when($filters['currency'] ?? null, fn ($q, $id) => $q->where('currency_id', $id))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('meta->category', $c))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('code', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")))
            ->get();
        $ids = $customers->pluck('id')->all();
        $balances = Ledger::balances($ids);
        $lines = fn () => JournalLine::query()->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->whereIn('journal_lines.account_id', $ids)->where('journal_lines.is_void', false)->groupBy('journal_lines.account_id');
        $lastPaid = $lines()->where('journal_lines.credit', '>', 0)->where('journal_entries.type', 'receipt')
            ->selectRaw('journal_lines.account_id as id, MAX(journal_lines.date) as d')->pluck('d', 'id');
        $lastSale = $lines()->where('journal_lines.debit', '>', 0)->selectRaw('journal_lines.account_id as id, MAX(journal_lines.date) as d')->pluck('d', 'id');
        $firstOwed = $lines()->where('journal_lines.debit', '>', 0)->selectRaw('journal_lines.account_id as id, MIN(journal_lines.date) as d')->pluck('d', 'id');

        $rows = $customers->map(function (Account $customer) use ($balances, $lastPaid, $lastSale, $firstOwed, $today) {
            $owed = $balances[$customer->id]['own'] ?? '0.00';
            if (! Num::isPositive($owed)) {
                return null;
            }
            $since = $lastPaid[$customer->id] ?? $firstOwed[$customer->id] ?? null;
            $sinceDay = $since ? substr((string) $since, 0, 10) : null;

            return [
                'account' => $customer,
                'owed' => $owed,
                'owed_base' => $balances[$customer->id]['base'] ?? '0.00',
                'last_paid' => isset($lastPaid[$customer->id]) ? substr((string) $lastPaid[$customer->id], 0, 10) : null,
                'last_sale' => isset($lastSale[$customer->id]) ? substr((string) $lastSale[$customer->id], 0, 10) : null,
                'days' => $sinceDay ? (int) \Carbon\CarbonImmutable::parse($sinceDay)->diffInDays($today) : 0,
            ];
        })->filter()->filter(fn ($row) => $row['days'] >= $days)->sortByDesc('days')->values();

        $byCurrency = $rows->groupBy(fn ($row) => $row['account']->currency->code)->map(fn ($group) => Num::money(Num::sum($group->pluck('owed'))));

        $query = JournalEntry::where('type', 'dead_cash')
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->when($filters['account'] ?? null, fn ($q, $id) => $q->whereHas('lines', fn ($l) => $l->where('account_id', $id)));

        $total = Num::money(JournalLine::where('account_id', Account::system('dead_cash')->id)->where('is_void', false)
            ->whereIn('journal_entry_id', (clone $query)->select('id'))
            ->selectRaw('COALESCE(SUM(base_debit), 0) - COALESCE(SUM(base_credit), 0) as total')->value('total') ?? '0');

        return view('money.dead-cash', [
            'rows' => $rows,
            'days' => $days,
            'dayOptions' => self::DAYS,
            'byCurrency' => $byCurrency,
            'totalBase' => Num::money(Num::sum($rows->pluck('owed_base'))),
            'currencies' => \App\Models\Currency::orderBy('id')->get(),
            'categories' => \App\Models\Lookup::options('customer_category'),
            'entries' => $query->with(['lines.account.currency', 'creator'])->orderByDesc('date')->orderByDesc('id')->paginate(per_page(25))->withQueryString(),
            'filters' => $filters,
            'total' => $total,
            'accounts' => $this->accounts(),
            'base' => Rates::base()->code,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('money.manage');

        $data = $request->validate([
            'date' => ['required', 'date'],
            'account_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
        ], [], ['account_id' => __('account'), 'reason' => __('reason')]);

        $account = $this->accounts()->firstWhere('id', (int) $data['account_id']) ?? throw new UserError(__('Choose a customer.'));
        $amount = Num::money($data['amount']);
        $balance = Ledger::balance($account->id);
        if (Num::cmp($amount, $balance) > 0) {
            throw new UserError(__(':name has only :balance. More than that cannot be written off.', ['name' => $account->name, 'balance' => money($balance, $account->currency->code)]));
        }

        $base = Rates::toBase($amount, $account->currency_id, $data['date']);
        $entry = DB::transaction(fn () => Ledger::post('dead_cash', $data['date'], __('Dead cash').': '.$account->name.' · '.$data['reason'], [
            ['account' => Account::system('dead_cash'), 'debit' => $base, 'base' => $base],
            ['account' => $account, 'credit' => $amount, 'base' => $base],
        ]));
        audit('created', $entry, $entry->number.' dead cash '.money($amount, $account->currency->code).' '.$account->name);

        return redirect()->route('dead-cash.index')->with('success', __('Saved as :number.', ['number' => $entry->number]));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('money.manage');

        $entry = JournalEntry::where('type', 'dead_cash')->findOrFail($request->route('id'));
        DB::transaction(fn () => Ledger::void($entry));
        audit('cancelled', $entry, $entry->number);

        return back()->with('success', __(':number was cancelled.', ['number' => $entry->number]));
    }

    /** Dead cash is written off customers' accounts. */
    private function accounts()
    {
        return Account::active()->whereNull('system_key')->with(['currency', 'type'])
            ->whereHas('type', fn ($q) => $q->where('key', 'customer'))
            ->orderBy('name')->get();
    }
}
