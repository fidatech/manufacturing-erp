<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\CrudController;
use App\Models\Account;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class WarehouseController extends CrudController
{
    protected string $model = Warehouse::class;

    protected string $route = 'warehouses';

    protected string $permission = 'setup';

    protected string $title = 'Warehouses';

    protected string $singular = 'Warehouse';

    protected string $icon = 'warehouse';

    protected array $with = ['manager'];

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150', Rule::unique('warehouses')->ignore($record?->id)]],
            ['name' => 'location', 'label' => 'Location', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'manager_account_id', 'label' => 'Warehouse manager', 'type' => 'select', 'rules' => ['nullable', 'exists:accounts,id'],
                'options' => Account::ofType('warehouse_manager')->orderBy('name')->pluck('name', 'id')->all()],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true, 'help' => 'Switch off to stop using it. It disappears from pick lists, but its history stays.'],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (Warehouse $w) => $w->name,
            'Location' => fn (Warehouse $w) => $w->location,
            'Warehouse manager' => fn (Warehouse $w) => $w->manager?->name,
            'Active' => fn (Warehouse $w) => $w->is_active ? __('Yes') : __('No'),
        ];
    }
}
