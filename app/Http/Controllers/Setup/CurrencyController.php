<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\CrudController;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrencyController extends CrudController
{
    protected string $model = Currency::class;

    protected string $route = 'currencies';

    protected string $permission = 'setup';

    protected string $title = 'Currencies';

    protected string $singular = 'Currency';

    protected string $icon = 'coins';

    protected string $orderBy = 'code';

    protected array $search = ['name', 'code'];

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'code', 'label' => 'Code', 'rules' => ['required', 'alpha', 'size:3', Rule::unique('currencies')->ignore($record?->id)], 'help' => 'Three letters, for example AFN', 'readonly' => (bool) $record],
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:100']],
            ['name' => 'symbol', 'label' => 'Symbol', 'rules' => ['nullable', 'string', 'max:10']],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true, 'help' => 'Switch off to stop using it. It disappears from pick lists, but its history stays.'],
        ];
    }

    protected function columns(): array
    {
        return [
            'Code' => fn (Currency $c) => $c->code,
            'Name' => fn (Currency $c) => $c->name.($c->is_base ? ' ('.__('main currency').')' : ''),
            'Symbol' => fn (Currency $c) => $c->symbol,
            'Active' => fn (Currency $c) => $c->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }
        if ($record?->is_base) {
            $data['is_active'] = true;
        }

        return $data;
    }

    protected function deleteProblem(Model $record): ?string
    {
        return $record->is_base ? __('The main currency cannot be deleted.') : null;
    }

    protected function rowLinks(Model $record): array
    {
        return $record->is_base ? [] : [['label' => __('Rates'), 'url' => route('rates.index', ['currency' => $record->id])]];
    }

}
