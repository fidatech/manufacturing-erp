<?php

namespace App\Http\Controllers\Trade;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Attachment;
use App\Models\Item;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\Ledger;
use App\Services\Rates;
use App\Services\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SaleController extends Controller
{
    public const TYPES = ['invoice', 'order', 'quotation', 'return'];

    public static function names(): array
    {
        return ['invoice' => __('Sale invoices'), 'order' => __('Sale orders'), 'quotation' => __('Sale quotations'), 'return' => __('Sale returns')];
    }

    public static function singular(string $type): string
    {
        return ['invoice' => __('Sale invoice'), 'order' => __('Sale order'), 'quotation' => __('Sale quotation'), 'return' => __('Sale return')][$type];
    }

    public function index(Request $request): View
    {
        Gate::authorize('sales.view');

        $type = in_array($request->query('type'), self::TYPES, true) ? $request->query('type') : 'invoice';
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'q' => ['nullable', 'string', 'max:100'], 'delivery' => ['nullable', 'in:pending,dispatched,delivered']]);

        $sales = Sale::with(['customer', 'currency'])
            ->where('type', $type)
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->when($filters['delivery'] ?? null, fn ($q, $status) => $q->where('status', 'posted')->where('delivery_status', $status))
            ->when($request->query('view') === 'dispatch', fn ($q) => $q->where('status', 'posted')->where('delivery_status', '!=', 'delivered'))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($i) => $i->where('number', 'like', "%$term%")->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$term%"))))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(per_page(25))->withQueryString();

        return view('sales.index', ['sales' => $sales, 'type' => $type, 'names' => self::names(), 'filters' => $filters, 'view' => in_array($request->query('view'), ['delivery', 'dispatch'], true) ? $request->query('view') : null]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('sales.manage');

        $type = in_array($request->query('type'), self::TYPES, true) ? $request->query('type') : 'invoice';
        $from = $request->query('from') ? Sale::with('lines')->find($request->query('from')) : null;

        return view('sales.form', [
            'type' => $type,
            'title' => self::singular($type),
            'from' => $from,
            'customers' => Account::ofType('customer')->active()->with('currency')->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'items' => Item::where('is_active', true)->with('unit')->orderBy('type')->orderBy('name')->get(),
            'moneyAccounts' => Account::money()->active()->with('currency')->orderBy('name')->get(),
            'base' => Rates::base()->code,
            // [customer id => [item id => price]] so the form can fill in a customer's own prices
            'prices' => \App\Models\PriceList::all()->groupBy('customer_id')->map(fn ($rows) => $rows->mapWithKeys(fn ($row) => [$row->item_id => \App\Support\Num::format($row->price, 6, true)]))->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('sales.manage');

        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'date' => ['required', 'date'],
            'customer_id' => ['required', 'exists:accounts,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'parent_id' => ['nullable', 'exists:sales,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'received' => ['nullable', 'numeric', 'min:0'],
            'received_in_id' => ['nullable', 'exists:accounts,id'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'transport_no' => ['nullable', 'string', 'max:100'],
            'driver' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array'],
            'lines.*.item_id' => ['nullable', 'exists:items,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $sale = SaleService::create($data);

        // the quotation or order this paper was made from is now done
        if (! empty($data['parent_id']) && in_array($data['type'], ['order', 'invoice'], true)) {
            Sale::where('id', $data['parent_id'])->whereIn('type', SaleService::OPEN_TYPES)->where('status', 'open')->update(['status' => 'converted']);
        }

        return redirect()->route('sales.show', ['id' => $sale->id])->with('success', __('Saved as :number.', ['number' => $sale->number]));
    }

    public function show(Request $request): View
    {
        Gate::authorize('sales.view');

        $sale = $this->find($request);

        return view('sales.show', [
            'sale' => $sale,
            'title' => self::singular($sale->type).' '.$sale->number,
            'balance' => Ledger::balance($sale->customer_id),
            'attachments' => Attachment::where('subject_type', 'Sale')->where('subject_id', $sale->id)->get(),
        ]);
    }

    /** The printed bill, or the delivery note (same paper without prices): ?paper=delivery */
    public function print(Request $request): View
    {
        Gate::authorize('sales.view');

        $sale = $this->find($request);

        return view('sales.print', [
            'sale' => $sale,
            'delivery' => $request->query('paper') === 'delivery',
            'title' => self::singular($sale->type).' '.$sale->number,
            'logo' => \App\Http\Controllers\Setup\CompanyController::logoDataUri(),
        ]);
    }

    /** Delivery tracking: pending, dispatched, delivered. */
    public function delivery(Request $request): RedirectResponse
    {
        Gate::authorize('sales.manage');

        $data = $request->validate([
            'delivery_status' => ['required', 'in:pending,dispatched,delivered'],
            'date' => ['nullable', 'date'],
            'transport_no' => ['nullable', 'string', 'max:100'],
            'driver' => ['nullable', 'string', 'max:150'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
        ]);

        $sale = Sale::where('type', 'invoice')->findOrFail($request->route('id'));
        if ($sale->status !== 'posted') {
            throw new UserError(__('A cancelled bill cannot be delivered.'));
        }

        $date = $data['date'] ?? today_iso();
        $sale->update([
            'delivery_status' => $data['delivery_status'],
            'dispatched_date' => $data['delivery_status'] === 'pending' ? null : ($sale->dispatched_date ?? $date),
            'delivered_date' => $data['delivery_status'] === 'delivered' ? $date : null,
            'transport_no' => $data['transport_no'] ?? $sale->transport_no,
            'driver' => $data['driver'] ?? $sale->driver,
            'delivery_address' => $data['delivery_address'] ?? $sale->delivery_address,
        ]);

        return back()->with('success', __('Delivery updated.'));
    }

    public function cancel(Request $request): RedirectResponse
    {
        Gate::authorize('sales.manage');

        $sale = Sale::findOrFail($request->route('id'));
        SaleService::cancel($sale);

        return back()->with('success', __(':number was cancelled.', ['number' => $sale->number]));
    }

    private function find(Request $request): Sale
    {
        return Sale::with(['customer.currency', 'warehouse', 'currency', 'lines.item.unit', 'receivedIn', 'parent'])->findOrFail($request->route('id'));
    }
}
