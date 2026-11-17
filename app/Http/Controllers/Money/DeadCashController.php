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
 * Dead cash: money that will not come back and is taken out of the books as a loss.
 * Torn, burnt or false notes in a cash box, money lost with a sarafi, or a customer's debt
 * that will never be paid. The amount leaves that account and goes to the expense "Dead cash".
 */
class DeadCashController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('money.view');

        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'account' => ['nullable', 'integer']]);

        $query = JournalEntry::where('type', 'dead_cash')
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->when($filters['account'] ?? null, fn ($q, $id) => $q->whereHas('lines', fn ($l) => $l->where('account_id', $id)));

        $total = Num::money(JournalLine::where('account_id', Account::system('dead_cash')->id)->where('is_void', false)
            ->whereIn('journal_entry_id', (clone $query)->select('id'))
            ->selectRaw('COALESCE(SUM(base_debit), 0) - COALESCE(SUM(base_credit), 0) as total')->value('total') ?? '0');

        return view('money.dead-cash', [
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

        $account = $this->accounts()->firstWhere('id', (int) $data['account_id']) ?? throw new UserError(__('Choose a cash box, bank, sarafi or customer.'));
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

    /** Where dead cash can come from: cash boxes, banks, sarafi and customers. */
    private function accounts()
    {
        return Account::active()->whereNull('system_key')->with(['currency', 'type'])
            ->whereHas('type', fn ($q) => $q->where('is_money', true)->orWhere('key', 'customer'))
            ->orderBy('name')->get();
    }
}
