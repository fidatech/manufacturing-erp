<?php

namespace App\Http\Controllers\Items;

use App\Http\Controllers\CrudController;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\Rates;
use App\Support\Num;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Products (/items/product) and raw materials (/items/raw) share this screen. */
class ItemController extends CrudController
{
    protected string $model = Item::class;

    protected string $route = 'items';

    protected string $permission = 'items';

    protected string $title = 'Products';

    protected string $singular = 'Product';

    protected string $icon = 'box';

    protected array $search = ['name', 'code'];

    protected array $with = ['unit', 'category'];

    private ?array $stock = null;

    protected function kind(): string
    {
        $kind = request()->route('type');
        abort_unless(in_array($kind, ['product', 'raw'], true), 404);

        [$this->title, $this->singular, $this->icon] = $kind === 'raw' ? ['Raw Materials', 'Raw Material', 'flask'] : ['Products', 'Product', 'box'];

        return $kind;
    }

    public function callAction($method, $parameters)
    {
        $this->stock = null;
        $this->kind();

        return parent::callAction($method, $parameters);
    }

    protected function routeParams(): array
    {
        return ['type' => $this->kind()];
    }

    protected function query(Request $request): Builder
    {
        return Item::query()->where('type', $this->kind());
    }

    protected function fields(?Model $record): array
    {
        $kind = $this->kind();
        $base = Rates::base()->code;

        $fields = [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:255']],
            ['name' => 'code', 'label' => 'Code', 'rules' => ['nullable', 'string', 'max:50', Rule::unique('items')->ignore($record?->id)], 'help' => 'For example: KC9012'],
            ['name' => 'item_category_id', 'label' => 'Group', 'type' => 'select', 'rules' => ['nullable', 'exists:item_categories,id'],
                'options' => ItemCategory::where('type', $kind)->orderBy('name')->pluck('name', 'id')->all()],
            ['name' => 'unit_id', 'label' => 'Counted in', 'type' => 'select', 'rules' => ['required', 'exists:units,id'],
                'options' => Unit::orderBy('name')->pluck('name', 'id')->all(), 'help' => 'Stock, purchases and sales of this item use this unit.'],
            ['name' => 'packing', 'label' => 'Packing', 'rules' => ['nullable', 'string', 'max:100'], 'help' => 'For example: Can 20 kg'],
        ];
        if ($kind === 'product') {
            $fields[] = ['name' => 'sale_price', 'label' => __('Usual sale price (:base)', ['base' => $base]), 'type' => 'decimal', 'rules' => ['nullable', 'numeric', 'min:0'], 'default' => 0];
        }
        $fields[] = ['name' => 'min_stock', 'label' => 'Warn me when stock is below', 'type' => 'decimal', 'rules' => ['nullable', 'numeric', 'min:0'], 'default' => 0];
        $fields[] = ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:2000'], 'col' => 12];
        $fields[] = ['name' => 'is_active', 'label' => 'In use', 'type' => 'checkbox', 'default' => true];

        return $fields;
    }

    protected function columns(): array
    {
        $base = Rates::base()->code;
        $columns = [
            'Name' => fn (Item $i) => $i->name.($i->packing ? ' · '.$i->packing : ''),
            'Code' => fn (Item $i) => $i->code,
            'Group' => fn (Item $i) => $i->category?->name,
            'In stock' => fn (Item $i) => qty($this->stockOf($i)).' '.($i->unit->symbol ?: $i->unit->name),
            'Average cost' => fn (Item $i) => Num::format($i->avg_cost, 4, true).' '.$base,
        ];
        if ($this->kind() === 'product') {
            $columns['Sale price'] = fn (Item $i) => money($i->sale_price, $base);
        }

        return $columns;
    }

    private function stockOf(Item $item): string
    {
        $this->stock ??= StockMovement::where('is_void', false)->groupBy('item_id')
            ->selectRaw('item_id, SUM(qty_in) - SUM(qty_out) as qty')->toBase()->pluck('qty', 'item_id')->all();

        return (string) ($this->stock[$item->id] ?? '0');
    }

    protected function rowLinks(Model $record): array
    {
        return \Illuminate\Support\Facades\Route::has('stock.card') ? [['label' => __('Stock card'), 'url' => route('stock.card', ['id' => $record->id])]] : [];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        if (! $record) {
            $data['type'] = $this->kind();
        }
        $data['sale_price'] = Num::money($data['sale_price'] ?? ($record->sale_price ?? 0));
        $data['min_stock'] = Num::qty($data['min_stock'] ?? 0);

        return $data;
    }

    protected function deleteProblem(Model $record): ?string
    {
        return StockMovement::where('item_id', $record->id)->exists() ? __('This item already has stock movements. Switch it off instead of deleting it.') : null;
    }
}
