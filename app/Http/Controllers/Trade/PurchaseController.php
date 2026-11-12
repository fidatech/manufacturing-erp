<?php

namespace App\Http\Controllers\Trade;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Attachment;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Warehouse;
use App\Services\PurchaseService;
use App\Services\Rates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public const TYPES = ['invoice', 'order', 'quotation', 'return'];

    public static function names(): array
    {
        return ['invoice' => __('Purchases'), 'order' => __('Purchase orders'), 'quotation' => __('Supplier quotations'), 'return' => __('Purchase returns')];
    }

    public static function singular(string $type): string
    {
        return ['invoice' => __('Purchase'), 'order' => __('Purchase order'), 'quotation' => __('Supplier quotation'), 'return' => __('Purchase return')][$type];
    }

    public function index(Request $request): View
    {
        Gate::authorize('purchases.view');

        $type = in_array($request->query('type'), self::TYPES, true) ? $request->query('type') : 'invoice';
        $filters = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'q' => ['nullable', 'string', 'max:100'], 'waiting' => ['nullable']]);

        $purchases = Purchase::with(['supplier', 'currency', 'warehouse'])
            ->where('type', $type)
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->when($request->boolean('waiting'), fn ($q) => $q->where('status', 'posted')->whereNull('received_date'))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($i) => $i->where('number', 'like', "%$term%")->orWhere('supplier_invoice_no', 'like', "%$term%")->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%$term%"))))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(per_page(25))->withQueryString();

        return view('purchases.index', ['purchases' => $purchases, 'type' => $type, 'names' => self::names(), 'filters' => $filters, 'grn' => $request->query('view') === 'grn']);
    }

    public function create(Request $request): View
    {
        Gate::authorize('purchases.manage');

        $type = in_array($request->query('type'), self::TYPES, true) ? $request->query('type') : 'invoice';
        // "from": an order being turned into a purchase, or a purchase being returned
        $from = $request->query('from') ? Purchase::with('lines')->find($request->query('from')) : null;

        return view('purchases.form', [
            'type' => $type,
            'title' => self::singular($type),
            'from' => $from,
            'suppliers' => Account::ofType('supplier')->active()->with('currency')->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'items' => Item::where('is_active', true)->with('unit')->orderBy('name')->get(),
            'moneyAccounts' => Account::money()->active()->with('currency')->orderBy('name')->get(),
            'base' => Rates::base()->code,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('purchases.manage');

        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', self::TYPES)],
            'date' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:accounts,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:60'],
            'parent_id' => ['nullable', 'exists:purchases,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid' => ['nullable', 'numeric', 'min:0'],
            'paid_from_id' => ['nullable', 'exists:accounts,id'],
            'received' => ['nullable'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array'],
            'lines.*.item_id' => ['nullable', 'exists:items,id'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.batch_no' => ['nullable', 'string', 'max:60'],
            'lines.*.mfg_date' => ['nullable', 'date'],
            'lines.*.expiry_date' => ['nullable', 'date'],
            'costs' => ['nullable', 'array'],
            'costs.*.name' => ['nullable', 'string', 'max:255'],
            'costs.*.amount' => ['nullable', 'numeric', 'min:0'],
            'costs.*.paid_from_id' => ['nullable', 'exists:accounts,id'],
        ]);
        $data['received'] = $request->boolean('received');

        $purchase = PurchaseService::create($data);

        // the quotation or order this paper was made from is now done
        if (! empty($data['parent_id']) && in_array($data['type'], ['order', 'invoice'], true)) {
            Purchase::where('id', $data['parent_id'])->whereIn('type', PurchaseService::OPEN_TYPES)->where('status', 'open')->update(['status' => 'converted']);
        }

        return redirect()->route('purchases.show', ['id' => $purchase->id])->with('success', __('Saved as :number.', ['number' => $purchase->number]));
    }

    public function show(Request $request): View
    {
        Gate::authorize('purchases.view');

        $purchase = Purchase::with(['supplier.currency', 'warehouse', 'currency', 'lines.item.unit', 'costs.paidFrom.currency', 'paidFrom', 'parent'])->findOrFail($request->route('id'));

        return view('purchases.show', [
            'purchase' => $purchase,
            'title' => self::singular($purchase->type).' '.$purchase->number,
            'base' => Rates::base()->code,
            'attachments' => Attachment::where('subject_type', 'Purchase')->where('subject_id', $purchase->id)->get(),
        ]);
    }

    /** Goods received (GRN). */
    public function receive(Request $request): RedirectResponse
    {
        Gate::authorize('purchases.manage');

        $data = $request->validate(['date' => ['required', 'date']]);
        $purchase = Purchase::findOrFail($request->route('id'));
        PurchaseService::receive($purchase, $data['date']);

        return back()->with('success', __('The goods are now in the warehouse.'));
    }

    public function cancel(Request $request): RedirectResponse
    {
        Gate::authorize('purchases.manage');

        $purchase = Purchase::findOrFail($request->route('id'));
        PurchaseService::cancel($purchase);

        return back()->with('success', __(':number was cancelled.', ['number' => $purchase->number]));
    }
}
