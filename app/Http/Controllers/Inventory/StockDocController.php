<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockDoc;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Rates;
use App\Services\StockDocService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Opening stock, transfers between warehouses and physical counts. */
class StockDocController extends Controller
{
    public const TYPES = ['transfer', 'count', 'opening'];

    public static function names(): array
    {
        return ['transfer' => __('Stock transfers'), 'count' => __('Physical counts'), 'opening' => __('Opening stock')];
    }

    public static function singular(string $type): string
    {
        return ['transfer' => __('Stock transfer'), 'count' => __('Physical count'), 'opening' => __('Opening stock')][$type];
    }

    private function type(Request $request): string
    {
        $type = $request->route('type');
        abort_unless(in_array($type, self::TYPES, true), 404);

        return $type;
    }

    public function index(Request $request): View
    {
        Gate::authorize('inventory.view');
        $type = $this->type($request);

        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'warehouse' => ['nullable', 'integer']]);

        $docs = StockDoc::with(['warehouse', 'toWarehouse'])->withCount('lines')->where('type', $type)
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->when($filters['warehouse'] ?? null, fn ($q, $id) => $q->where(fn ($w) => $w->where('warehouse_id', $id)->orWhere('to_warehouse_id', $id)))
            ->orderByDesc('date')->orderByDesc('id')->paginate(per_page(25))->withQueryString();

        return view('stock.docs', ['filters' => $filters, 'warehouses' => \App\Models\Warehouse::orderBy('name')->get(), 'docs' => $docs, 'type' => $type, 'title' => self::names()[$type], 'singular' => self::singular($type)]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('inventory.manage');
        $type = $this->type($request);

        // a physical count starts from the list of what the system thinks is in the warehouse
        $warehouseId = (int) $request->query('warehouse');
        $system = $type === 'count' && $warehouseId
            ? StockMovement::where('is_void', false)->where('warehouse_id', $warehouseId)->groupBy('item_id')->selectRaw('item_id, SUM(qty_in) - SUM(qty_out) as qty')->toBase()->pluck('qty', 'item_id')
            : collect();

        return view('stock.doc-form', [
            'type' => $type,
            'title' => self::singular($type),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'items' => Item::where('is_active', true)->with('unit')->orderBy('type')->orderBy('name')->get(),
            'warehouseId' => $warehouseId,
            'system' => $system,
            'base' => Rates::base()->code,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('inventory.manage');
        $type = $this->type($request);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array'],
            'lines.*.item_id' => ['nullable', 'exists:items,id'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.batch_no' => ['nullable', 'string', 'max:60'],
            'lines.*.mfg_date' => ['nullable', 'date'],
            'lines.*.expiry_date' => ['nullable', 'date'],
        ]);

        $doc = StockDocService::create($data + ['type' => $type]);

        return redirect()->route('stock-docs.show', ['type' => $type, 'id' => $doc->id])->with('success', __('Saved as :number.', ['number' => $doc->number]));
    }

    public function show(Request $request): View
    {
        Gate::authorize('inventory.view');
        $type = $this->type($request);

        $doc = StockDoc::with(['warehouse', 'toWarehouse', 'lines.item.unit'])->where('type', $type)->findOrFail($request->route('id'));

        return view('stock.doc-show', ['doc' => $doc, 'type' => $type, 'title' => self::singular($type).' '.$doc->number, 'base' => Rates::base()->code]);
    }

    public function cancel(Request $request): RedirectResponse
    {
        Gate::authorize('inventory.manage');

        $doc = StockDoc::where('type', $this->type($request))->findOrFail($request->route('id'));
        StockDocService::cancel($doc);

        return back()->with('success', __(':number was cancelled.', ['number' => $doc->number]));
    }
}
