<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\ProductionBatch;
use App\Models\Recipe;
use App\Models\Warehouse;
use App\Services\ProductionService;
use App\Services\Rates;
use App\Support\Num;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductionController extends Controller
{
    /** Daily production record: every batch that was made. */
    public function index(Request $request): View
    {
        Gate::authorize('production.view');

        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'q' => ['nullable', 'string', 'max:100'], 'qc' => ['nullable', 'in:pending,passed,failed']]);

        $batches = ProductionBatch::with(['item.unit', 'toWarehouse'])
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->when($filters['qc'] ?? null, fn ($q, $status) => $q->where('status', 'posted')->where('qc_status', $status))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($i) => $i->where('number', 'like', "%$term%")->orWhere('batch_no', 'like', "%$term%")->orWhereHas('item', fn ($p) => $p->where('name', 'like', "%$term%")->orWhere('code', 'like', "%$term%"))))
            ->orderByDesc('date')->orderByDesc('id')->paginate(per_page(25))->withQueryString();

        return view('production.index', ['batches' => $batches, 'filters' => $filters, 'base' => Rates::base()->code]);
    }

    /** Step 1: choose the product and how many. Step 2 (same page): check the materials and save. */
    public function create(Request $request): View
    {
        Gate::authorize('production.manage');

        $picked = $request->validate(['recipe' => ['nullable', 'integer'], 'qty' => ['nullable', 'numeric', 'gt:0'], 'warehouse' => ['nullable', 'integer']]);
        $recipe = ! empty($picked['recipe']) ? Recipe::with(['lines.item.unit', 'item.unit'])->find($picked['recipe']) : null;
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $warehouseId = (int) ($picked['warehouse'] ?? ($warehouses->count() === 1 ? $warehouses->first()->id : 0));
        $qty = $picked['qty'] ?? null;

        return view('production.form', [
            'recipes' => Recipe::with('item')->where('is_active', true)->orderBy('name')->get(),
            'recipe' => $recipe,
            'qty' => $qty,
            'warehouses' => $warehouses,
            'warehouseId' => $warehouseId,
            'needs' => $recipe && $qty ? ProductionService::needs($recipe, $qty, $warehouseId ?: null) : [],
            'water' => $recipe && $qty ? Num::qty(Num::mul($recipe->water(), $qty)) : '0',
            'base' => Rates::base()->code,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('production.manage');

        $data = $request->validate([
            'recipe_id' => ['required', 'exists:recipes,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id'],
            'batch_no' => ['nullable', 'string', 'max:60'],
            'expiry_date' => ['nullable', 'date', 'after:date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'used' => ['nullable', 'array'],
            'used.*' => ['nullable', 'numeric', 'min:0'],
        ], [], ['qty' => __('quantity made'), 'warehouse_id' => __('warehouse of the raw materials'), 'to_warehouse_id' => __('warehouse of the finished product')]);

        $batch = ProductionService::create($data);

        return redirect()->route('production.show', ['id' => $batch->id])->with('success', __('Saved as :number.', ['number' => $batch->number]));
    }

    public function show(Request $request): View
    {
        Gate::authorize('production.view');

        $batch = ProductionBatch::with(['item.unit', 'recipe', 'warehouse', 'toWarehouse', 'lines.item.unit', 'tests'])->findOrFail($request->route('id'));

        return view('production.show', ['batch' => $batch, 'base' => Rates::base()->code]);
    }

    public function cancel(Request $request): RedirectResponse
    {
        Gate::authorize('production.manage');

        $batch = ProductionBatch::findOrFail($request->route('id'));
        ProductionService::cancel($batch);

        return back()->with('success', __(':number was cancelled.', ['number' => $batch->number]));
    }

    /** What was made between two dates, by product, and what it cost. */
    public function report(Request $request): View
    {
        Gate::authorize('production.view');

        $dates = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $from = $dates['from'] ?? now()->startOfMonth()->toDateString();
        $to = $dates['to'] ?? today_iso();

        $batches = fn () => DB::table('production_batches')->where('production_batches.status', 'posted')
            ->whereDate('production_batches.date', '>=', $from)->whereDate('production_batches.date', '<=', $to);

        $products = $batches()->join('items', 'items.id', '=', 'production_batches.item_id')->groupBy('items.id', 'items.name', 'items.code')
            ->selectRaw('items.name as name, items.code as code, COUNT(*) as batches, SUM(production_batches.qty) as qty, SUM(production_batches.cost_base) as cost')
            ->orderByDesc('qty')->get();
        $materials = DB::table('production_lines')->join('production_batches', 'production_batches.id', '=', 'production_lines.production_batch_id')
            ->join('items', 'items.id', '=', 'production_lines.item_id')->where('production_batches.status', 'posted')
            ->whereDate('production_batches.date', '>=', $from)->whereDate('production_batches.date', '<=', $to)
            ->groupBy('items.id', 'items.name')->selectRaw('items.name as name, SUM(production_lines.qty) as qty, SUM(production_lines.cost_base) as cost')
            ->orderByDesc('cost')->get();

        return view('production.report', ['from' => $from, 'to' => $to, 'base' => Rates::base()->code, 'products' => $products, 'materials' => $materials]);
    }
}
