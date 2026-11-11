<?php

namespace App\Http\Controllers\Money;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\Ledger;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Day book (roznamcha): everything that was posted, day by day. Plus hand-written entries. */
class JournalController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('journal.view');

        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'q' => ['nullable', 'string', 'max:100']]);
        $from = $filters['from'] ?? today_iso();
        $to = $filters['to'] ?? today_iso();

        $entries = JournalEntry::with(['lines.account.currency', 'creator'])
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($i) => $i->where('number', 'like', "%$term%")->orWhere('description', 'like', "%$term%")))
            ->orderBy('date')->orderBy('id')
            ->paginate(50)->withQueryString();

        return view('journal.index', ['entries' => $entries, 'from' => $from, 'to' => $to, 'term' => $filters['q'] ?? '']);
    }

    public function create(): View
    {
        Gate::authorize('journal.manage');

        return view('journal.form', ['accounts' => Account::active()->with(['currency', 'type'])->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('journal.manage');

        $data = $request->validate([
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['nullable', 'exists:accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $lines = [];
        foreach ($data['lines'] as $line) {
            $debit = Num::money($line['debit'] ?? 0);
            $credit = Num::money($line['credit'] ?? 0);
            if (empty($line['account_id']) || (Num::isZero($debit) && Num::isZero($credit))) {
                continue;
            }
            if (Num::isPositive($debit) && Num::isPositive($credit)) {
                throw new UserError(__('A line can have a debit or a credit, not both.'));
            }
            $lines[] = ['account' => (int) $line['account_id'], 'debit' => $debit, 'credit' => $credit];
        }

        $entry = Ledger::post('journal', $data['date'], $data['description'], $lines);
        audit('created', $entry, $entry->number.' '.$data['description']);

        return redirect()->route('journal.index', ['from' => $data['date'], 'to' => $data['date']])->with('success', __('Saved as :number.', ['number' => $entry->number]));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('journal.manage');

        $entry = JournalEntry::where('type', 'journal')->findOrFail($request->route('id'));
        DB::transaction(fn () => Ledger::void($entry));

        return back()->with('success', __(':number was cancelled.', ['number' => $entry->number]));
    }
}
