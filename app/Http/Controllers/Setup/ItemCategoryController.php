<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\CrudController;
use App\Models\ItemCategory;
use Illuminate\Database\Eloquent\Model;

class ItemCategoryController extends CrudController
{
    protected string $model = ItemCategory::class;

    protected string $route = 'item-categories';

    protected string $permission = 'items';

    protected string $title = 'Item Groups';

    protected string $singular = 'Group';

    protected string $icon = 'tags';

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150'], 'help' => 'For example: Flavors, Preservatives, Colors'],
            ['name' => 'type', 'label' => 'For', 'type' => 'select', 'options' => ['raw' => __('Raw materials'), 'product' => __('Products')], 'rules' => ['required', 'in:raw,product'], 'default' => request('type', 'raw')],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (ItemCategory $c) => $c->name,
            'For' => fn (ItemCategory $c) => $c->type === 'raw' ? __('Raw materials') : __('Products'),
        ];
    }
}
