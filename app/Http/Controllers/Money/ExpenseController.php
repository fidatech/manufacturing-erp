<?php

namespace App\Http\Controllers\Money;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Currency;
use App\Models\JournalLine;
use App\Services\Rates;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Expenses on their own page: every expense paid from a cash box, bank or sarafi,
 * filtered by currency, expense category and dates, with totals by category.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('money.view');

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'category' => ['nullable', 'integer'],
            'currency' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? today_iso();

        // one row for every expense line: the category that was charged
        $query = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('account_types', 'account_types.id', '=', 'accounts.account_type_id')
            ->where('journal_entries.type', 'expense')
            ->where('account_types.nature', 'expense')
            ->where('journal_lines.debit', '>', 0)
            ->whereBetween('journal_lines.date', [$from, $to])
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->where('journal_lines.account_id', $id))
            ->when($filters['currency'] ?? null, fn ($q, $id) => $q->whereExists(fn ($e) => $e->selectRaw('1')->from('journal_lines as paid')
                ->join('accounts as paid_account', 'paid_account.id', '=', 'paid.account_id')
                ->whereColumn('paid.journal_entry_id', 'journal_entries.id')->where('paid.credit', '>', 0)->where('paid_account.currency_id', $id)))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('journal_entries.number', 'like', "%$t%")->orWhere('journal_entries.description', 'like', "%$t%")));

        $counted = (clone $query)->whereNull('journal_entries.voided_at')->where('journal_lines.is_void', false);
        $byCategory = (clone $counted)->groupBy('accounts.id', 'accounts.name')
            ->selectRaw('accounts.id as id, accounts.name as name, COUNT(*) as entries, COALESCE(SUM(journal_lines.base_debit), 0) as total')
            ->orderByDesc('total')->toBase()->get();
        $total = Num::money(Num::sum($byCategory->pluck('total')));

        $lines = $query->select('journal_lines.*', 'journal_entries.number as entry_number', 'journal_entries.description as entry_description', 'journal_entries.voided_at as entry_voided_at')
            ->with(['account', 'entry.lines.account.currency', 'entry.creator'])
            ->orderByDesc('journal_lines.date')->orderByDesc('journal_lines.id')
            ->paginate(per_page(25))->withQueryString();

        return view('money.expenses', [
            'lines' => $lines,
            'filters' => $filters + ['from' => $from, 'to' => $to],
            'from' => $from,
            'to' => $to,
            'byCategory' => $byCategory,
            'total' => $total,
            'base' => Rates::base()->code,
            'categories' => Account::ofType('expense')->orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::orderBy('id')->get(),
        ]);
    }
}
