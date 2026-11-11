<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Services\Ledger;
use App\Services\Rates;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The first screen: the numbers that matter today and the things that need attention. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $data = ['base' => Rates::base()->code, 'money' => null, 'owed' => null, 'stock' => null, 'sales' => null, 'low' => [], 'waiting' => collect(), 'toDeliver' => collect(), 'expiring' => collect(), 'toTest' => collect()];

        if ($user->can('production.view')) {
            $data['toTest'] = \App\Models\ProductionBatch::with('item')->where('status', 'posted')->whereIn('qc_status', ['pending', 'failed'])->orderByDesc('date')->limit(8)->get();
        }

        $balances = ($user->can('money.view') || $user->can('accounts.view')) ? Ledger::balances() : [];

        if ($user->can('money.view')) {
            $data['money'] = Account::money()->active()->with('currency')->orderBy('name')->get()
                ->map(fn (Account $a) => ['account' => $a, 'balance' => $balances[$a->id]['own'] ?? '0.00'])
                ->filter(fn ($row) => ! Num::isZero($row['balance']) || $row['account']->type?->key === 'cash')->values();
        }

        if ($user->can('accounts.view')) {
            $owesUs = '0';
            $weOwe = '0';
            foreach (Account::with('type')->whereHas('type', fn ($q) => $q->where('is_party', true)->where('is_money', false))->get() as $account) {
                $base = $balances[$account->id]['base'] ?? '0';
                if (Num::isPositive($base)) {
                    $owesUs = Num::add($owesUs, $base);
                } elseif (in_array($account->type->key, ['supplier', 'employee', 'liability'], true) || $account->type->nature === 'liability') {
                    $weOwe = Num::add($weOwe, Num::abs($base));
                }
            }
            $data['owed'] = ['us' => Num::money($owesUs), 'them' => Num::money($weOwe)];
        }

        if ($user->can('inventory.view')) {
            $stock = StockMovement::where('is_void', false)->groupBy('item_id')->selectRaw('item_id, SUM(qty_in) - SUM(qty_out) as qty')->toBase()->pluck('qty', 'item_id');
            $worth = '0';
            foreach (Item::where('is_active', true)->with('unit')->get() as $item) {
                $qty = Num::qty($stock[$item->id] ?? 0);
                $worth = Num::add($worth, Num::mul($qty, $item->avg_cost));
                if (Num::isPositive($item->min_stock) && Num::cmp($qty, $item->min_stock) < 0) {
                    $data['low'][] = ['item' => $item, 'qty' => $qty];
                }
            }
            $data['stock'] = Num::money($worth);
            $data['low'] = array_slice($data['low'], 0, 8);

            $data['expiring'] = StockMovement::where('is_void', false)->whereNotNull('expiry_date')
                ->groupBy('item_id', 'batch_no')->selectRaw('item_id, batch_no, MIN(expiry_date) as expiry_date, SUM(qty_in) - SUM(qty_out) as qty')
                ->toBase()->get()
                ->filter(fn ($row) => Num::isPositive($row->qty) && substr((string) $row->expiry_date, 0, 10) <= now()->addDays(60)->toDateString())
                ->sortBy('expiry_date')->take(8)->values();
            $data['expiringItems'] = Item::whereIn('id', $data['expiring']->pluck('item_id')->unique())->get()->keyBy('id');
        }

        if ($user->can('sales.view')) {
            $sum = fn ($query) => Num::money($query->where('type', 'invoice')->where('status', 'posted')->selectRaw('COALESCE(SUM(total * 1.0 / rate), 0) as total')->toBase()->value('total'));
            $data['sales'] = [
                'today' => $sum(Sale::whereDate('date', today_iso())),
                'month' => $sum(Sale::whereDate('date', '>=', now()->startOfMonth()->toDateString())),
            ];
            $data['toDeliver'] = Sale::with('customer')->where('type', 'invoice')->where('status', 'posted')->where('delivery_status', '!=', 'delivered')->orderBy('date')->limit(8)->get();
        }

        if ($user->can('purchases.view')) {
            $data['waiting'] = Purchase::with('supplier')->where('type', 'invoice')->where('status', 'posted')->whereNull('received_date')->orderBy('date')->limit(8)->get();
        }

        return view('dashboard', $data);
    }
}
