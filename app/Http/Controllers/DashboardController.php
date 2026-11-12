<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Services\Ledger;
use App\Services\Rates;
use App\Support\Chart;
use App\Support\Num;
use App\Support\Periods;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** The first screen: the numbers that matter today and the things that need attention. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $data = ['base' => Rates::base()->code, 'money' => null, 'owed' => null, 'stock' => null, 'sales' => null, 'low' => [], 'waiting' => collect(), 'toDeliver' => collect(), 'expiring' => collect(), 'toTest' => collect(), 'daily' => null, 'monthly' => null, 'top' => null];

        if ($user->can('production.view')) {
            $data['toTest'] = \App\Models\ProductionBatch::with('item')->where('status', 'posted')->whereIn('qc_status', ['pending', 'failed'])->orderByDesc('date')->limit(8)->get();
        }

        $balances = ($user->can('money.view') || $user->can('accounts.view')) ? Ledger::balances() : [];

        if ($user->can('money.view')) {
            $data['money'] = Account::money()->active()->with('currency')->orderBy('name')->get()
                ->map(fn (Account $a) => ['account' => $a, 'balance' => $balances[$a->id]['own'] ?? '0.00', 'base' => $balances[$a->id]['base'] ?? '0.00'])
                ->filter(fn ($row) => ! Num::isZero($row['balance']) || $row['account']->type?->key === 'cash')->values();
            $data['moneyTotal'] = Num::money(Num::sum($data['money']->pluck('base')->all()));
            $data['moneyMax'] = $data['money']->reduce(fn ($max, $row) => Num::cmp(Num::abs($row['base']), $max) > 0 ? Num::abs($row['base']) : $max, '0');
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

        // Charts: sales day by day, sales against purchases month by month, best sellers of the month
        $months = Periods::months(6);
        $byDay = fn (string $model) => $model::where('type', 'invoice')->where('status', 'posted')->whereDate('date', '>=', $months[0]['from'])
            ->groupBy('date')->selectRaw('date, COALESCE(SUM(total * 1.0 / rate), 0) as total')->toBase()->get()
            ->mapWithKeys(fn ($row) => [substr((string) $row->date, 0, 10) => Num::money($row->total)])->all();
        $inMonth = function (array $days, array $month) {
            $total = '0';
            foreach ($days as $date => $amount) {
                if ($date >= $month['from'] && $date <= $month['to']) {
                    $total = Num::add($total, $amount);
                }
            }

            return Num::money($total);
        };

        $sold = $user->can('sales.view') ? $byDay(Sale::class) : null;
        $bought = $user->can('purchases.view') ? $byDay(Purchase::class) : null;

        if ($sold !== null) {
            $days = [];
            for ($i = 29; $i >= 0; $i--) {
                $date = now()->subDays($i)->toDateString();
                $days[] = ['date' => $date, 'value' => $sold[$date] ?? '0.00'];
            }
            $data['daily'] = ['days' => $days, 'total' => Num::money(Num::sum(array_column($days, 'value'))), 'scale' => Chart::scale(array_column($days, 'value'))];

            $month = end($months);
            $data['top'] = DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')->join('items', 'items.id', '=', 'sale_lines.item_id')
                ->where('sales.type', 'invoice')->where('sales.status', 'posted')->whereDate('sales.date', '>=', $month['from'])
                ->groupBy('items.id', 'items.name')
                ->selectRaw('items.id, items.name, COALESCE(SUM(sale_lines.total * 1.0 / sales.rate), 0) as total')
                ->orderByDesc('total')->limit(5)->get()
                ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name, 'total' => Num::money($row->total)])->all();
        }

        if ($sold !== null || $bought !== null) {
            $rows = array_map(fn (array $month) => [
                'label' => $month['label'],
                'sold' => $sold !== null ? $inMonth($sold, $month) : null,
                'bought' => $bought !== null ? $inMonth($bought, $month) : null,
            ], $months);
            $data['monthly'] = ['months' => $rows, 'scale' => Chart::scale(array_merge(array_column($rows, 'sold'), array_column($rows, 'bought')))];
        }

        return view('dashboard', $data);
    }
}
