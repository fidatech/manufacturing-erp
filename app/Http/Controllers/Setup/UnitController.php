<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\CrudController;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class UnitController extends CrudController
{
    protected string $model = Unit::class;

    protected string $route = 'units';

    protected string $permission = 'setup';

    protected string $title = 'Units';

    protected string $singular = 'Unit';

    protected string $icon = 'scale-balanced';

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:100', Rule::unique('units')->ignore($record?->id)]],
            ['name' => 'symbol', 'label' => 'Short name', 'rules' => ['nullable', 'string', 'max:20'], 'help' => 'For example: kg'],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (Unit $unit) => $unit->name,
            'Short name' => fn (Unit $unit) => $unit->symbol,
        ];
    }
}
