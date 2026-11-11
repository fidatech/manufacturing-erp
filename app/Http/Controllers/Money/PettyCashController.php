<?php

namespace App\Http\Controllers\Money;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\Ledger;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Petty cash: the cash boxes, what is in them and the small expenses paid from them. */
class PettyCashController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('money.view');

        $boxes = Account::ofType('cash')->active()->with('currency')->orderBy('name')->get();
        $ids = $boxes->pluck('id');

        return view('money.petty-cash', [
            'boxes' => $boxes,
            'balances' => Ledger::balances($ids->all()),
            'entries' => JournalEntry::with(['lines.account.currency'])
                ->whereNull('voided_at')
                ->whereHas('lines', fn ($q) => $q->whereIn('account_id', $ids))
                ->orderByDesc('date')->orderByDesc('id')->limit(30)->get(),
        ]);
    }
}
