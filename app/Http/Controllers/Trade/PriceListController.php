<?php

namespace App\Http\Controllers\Trade;

use App\Exceptions\UserError;
use App\Http\Controllers\CrudController;
use App\Models\Account;
use App\Models\Item;
use App\Models\PriceList;
use App\Support\Num;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** A customer's own price for an item. The sale form fills it in by itself. */
class PriceListController extends CrudController
{
    protected string $model = PriceList::class;

    protected string $route = 'price-lists';

    protected string $permission = 'sales';

    protected string $title = 'Customer Price Lists';

    protected string $singular = 'Price';

    protected string $icon = 'tags';

    protected string $orderBy = 'customer_id';

    protected array $search = [];

    protected array $with = ['customer.currency', 'item'];

    protected function query(Request $request): Builder
    {
        $term = trim((string) $request->query('q'));

        return PriceList::query()
            ->when((int) $request->query('customer'), fn ($q, $id) => $q->where('customer_id', $id))
            ->when($term !== '', fn ($q) => $q->where(fn ($i) => $i
            ->whereHas('customer', fn ($c) => $c->where('name', 'like', "%$term%"))
            ->orWhereHas('item', fn ($c) => $c->where('name', 'like', "%$term%")->orWhere('code', 'like', "%$term%"))));
    }

    protected function filters(Request $request): array
    {
        return [
            ['name' => 'customer', 'label' => __('All customers'), 'value' => (string) $request->query('customer'),
                'options' => Account::whereIn('id', PriceList::query()->select('customer_id'))->orderBy('name')->get()->mapWithKeys(fn (Account $a) => [$a->id => $a->name])->all()],
        ];
    }

    /** New prices are written like the items of a sale: one customer, then a row for every item. */
    public function create(Request $request): View
    {
        Gate::authorize($this->permission.'.manage');

        return view('sales.price-list-form', $this->shared() + [
            'customers' => Account::ofType('customer')->active()->with('currency')->orderBy('name')->get(),
            'items' => Item::where('is_active', true)->with('unit')->orderBy('type')->orderBy('name')->get(),
            'startLines' => old('lines', [[]]),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        // the single form (one item) is still used when a price is changed
        if (! $request->has('lines')) {
            return parent::store($request);
        }
        Gate::authorize($this->permission.'.manage');

        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('accounts', 'id')],
            'lines' => ['required', 'array'],
            'lines.*.item_id' => ['nullable', 'exists:items,id'],
            'lines.*.price' => ['nullable', 'numeric', 'min:0'],
        ], [], ['customer_id' => __('customer')]);

        $customer = Account::ofType('customer')->findOrFail($data['customer_id']);
        $prices = [];
        foreach ($data['lines'] as $line) {
            if (empty($line['item_id']) || ! isset($line['price']) || $line['price'] === '') {
                continue;
            }
            if (isset($prices[$line['item_id']])) {
                throw new UserError(__('The same item is written twice.'));
            }
            $prices[$line['item_id']] = Num::round($line['price'], 6);
        }
        if (! $prices) {
            throw new UserError(__('Add at least one item with a price.'));
        }

        // an item that already has a price for this customer gets the new price
        DB::transaction(function () use ($customer, $prices) {
            foreach ($prices as $itemId => $price) {
                PriceList::updateOrCreate(['customer_id' => $customer->id, 'item_id' => $itemId], ['price' => $price]);
            }
        });

        return redirect()->route('price-lists.index')->with('success', __(':count prices saved for :name.', ['count' => count($prices), 'name' => $customer->name]));
    }

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'customer_id', 'label' => 'Customer', 'type' => 'select', 'search' => true, 'rules' => ['required', 'exists:accounts,id'],
                'options' => Account::ofType('customer')->active()->with('currency')->orderBy('name')->get()->mapWithKeys(fn (Account $a) => [$a->id => $a->label()])->all()],
            ['name' => 'item_id', 'label' => 'Item', 'type' => 'select', 'search' => true,
                'rules' => ['required', 'exists:items,id', Rule::unique('price_lists')->where('customer_id', request('customer_id'))->ignore($record?->id)],
                'options' => Item::where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn (Item $i) => [$i->id => $i->label()])->all()],
            ['name' => 'price', 'label' => 'Price for this customer', 'type' => 'decimal', 'rules' => ['required', 'numeric', 'min:0'], 'help' => 'In the currency of the customer\'s account.'],
        ];
    }

    protected function columns(): array
    {
        return [
            'Customer' => fn (PriceList $p) => $p->customer->name,
            'Item' => fn (PriceList $p) => $p->item->label(),
            'Price' => fn (PriceList $p) => Num::format($p->price, 6, true).' '.$p->customer->currency->code,
        ];
    }
}
