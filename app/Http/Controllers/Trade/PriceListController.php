<?php

namespace App\Http\Controllers\Trade;

use App\Http\Controllers\CrudController;
use App\Models\Account;
use App\Models\Item;
use App\Models\PriceList;
use App\Support\Num;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        return PriceList::query()->when($term !== '', fn ($q) => $q->where(fn ($i) => $i
            ->whereHas('customer', fn ($c) => $c->where('name', 'like', "%$term%"))
            ->orWhereHas('item', fn ($c) => $c->where('name', 'like', "%$term%")->orWhere('code', 'like', "%$term%"))));
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
