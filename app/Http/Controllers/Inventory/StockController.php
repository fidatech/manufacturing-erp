<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Rates;
use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StockController extends Controller
{
    public static function movementNames(): array
    {
        return [
            'purchase' => __('Purchase'), 'purchase_return' => __('Purchase return'), 'sale' => __('Sale'), 'sale_return' => __('Sale return'),
            'opening' => __('Opening stock'), 'transfer_in' => __('Transfer in'), 'transfer_out' => __('Transfer out'), 'count' => __('Physical count'),
            'production_in' => __('Produced'), 'production_out' => __('Used in production'),
        ];
    }

    /** Stock on hand: what we have, where, and what it is worth. */
    public function index(Request $request): View
    {
        Gate::authorize('inventory.view');

        $filters = $request->validate(['warehouse' => ['nullable', 'integer'], 'type' => ['nullable', 'in:product,raw'], 'q' => ['nullable', 'string', 'max:100'], 'low' => ['nullable']]);

        $stock = StockMovement::where('is_void', false)
            ->when($filters['warehouse'] ?? null, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(qty_in) - SUM(qty_out) as qty')
            ->toBase()->pluck('qty', 'item_id');

        $items = Item::with(['unit', 'category'])
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($i) => $i->where('name', 'like', "%$term%")->orWhere('code', 'like', "%$term%")))
            ->orderBy('type')->orderBy('name')
            ->get();

        $rows = [];
        $value = '0';
        foreach ($items as $item) {
            $qty = Num::qty($stock[$item->id] ?? 0);
            $low = Num::isPositive($item->min_stock) && Num::cmp($qty, $item->min_stock) < 0;
            if ($request->boolean('low') && ! $low) {
                continue;
            }
            if (! $item->is_active && Num::isZero($qty)) {
                continue;
            }
            $worth = Num::money(Num::mul($qty, $item->avg_cost));
            $value = Num::add($value, $worth);
            $rows[] = ['item' => $item, 'qty' => $qty, 'low' => $low, 'value' => $worth];
        }

        return view('stock.index', [
            'rows' => $rows,
            'value' => $value,
            'filters' => $filters,
            'warehouses' => Warehouse::orderBy('name')->get(),
            'base' => Rates::base()->code,
        ]);
    }

    /** Stock card of one item: every movement with the quantity left after it. */
    public function card(Request $request): View
    {
        Gate::authorize('inventory.view');

        $item = Item::with('unit')->findOrFail($request->route('id'));
        $filters = $request->validate(['warehouse' => ['nullable', 'integer'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $from = $filters['from'] ?? now()->startOfYear()->toDateString();
        $to = $filters['to'] ?? today_iso();

        $base = fn () => StockMovement::where('item_id', $item->id)->where('is_void', false)
            ->when($filters['warehouse'] ?? null, fn ($q, $id) => $q->where('warehouse_id', $id));

        $before = $base()->whereDate('date', '<', $from)->selectRaw('COALESCE(SUM(qty_in),0) - COALESCE(SUM(qty_out),0) as qty')->toBase()->value('qty');
        $running = Num::qty($before ?? 0);
        $opening = $running;

        $rows = [];
        foreach ($base()->with('warehouse')->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->orderBy('date')->orderBy('id')->get() as $movement) {
            $running = Num::qty(Num::add($running, Num::sub($movement->qty_in, $movement->qty_out)));
            $rows[] = ['movement' => $movement, 'balance' => $running];
        }

        return view('stock.card', [
            'item' => $item,
            'rows' => $rows,
            'opening' => $opening,
            'closing' => $running,
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'warehouses' => Warehouse::orderBy('name')->get(),
            'names' => self::movementNames(),
        ]);
    }

    /** Batches with their expiry dates: what must be used or sold first. */
    public function batches(Request $request): View
    {
        Gate::authorize('inventory.view');

        $filters = $request->validate(['warehouse' => ['nullable', 'integer'], 'days' => ['nullable', 'integer', 'min:1', 'max:3650']]);

        $rows = StockMovement::where('is_void', false)
            ->when($filters['warehouse'] ?? null, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->groupBy('item_id', 'warehouse_id', 'batch_no')
            ->selectRaw('item_id, warehouse_id, batch_no, MIN(mfg_date) as mfg_date, MIN(expiry_date) as expiry_date, SUM(qty_in) - SUM(qty_out) as qty')
            ->toBase()->get()
            ->filter(fn ($row) => Num::isPositive($row->qty))
            ->when($filters['days'] ?? null, fn ($rows, $days) => $rows->filter(fn ($row) => $row->expiry_date && substr((string) $row->expiry_date, 0, 10) <= now()->addDays((int) $days)->toDateString()))
            ->sortBy(fn ($row) => $row->expiry_date ?? '9999-12-31')
            ->values();

        return view('stock.batches', [
            'rows' => $rows,
            'items' => Item::with('unit')->whereIn('id', $rows->pluck('item_id')->unique())->get()->keyBy('id'),
            'warehouses' => Warehouse::orderBy('name')->get()->keyBy('id'),
            'filters' => $filters,
            'today' => today_iso(),
        ]);
    }
}
