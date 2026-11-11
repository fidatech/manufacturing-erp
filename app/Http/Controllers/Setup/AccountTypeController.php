<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\CrudController;
use App\Models\AccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AccountTypeController extends CrudController
{
    protected string $model = AccountType::class;

    protected string $route = 'account-types';

    protected string $permission = 'setup';

    protected string $title = 'Account Types';

    protected string $singular = 'Account Type';

    protected string $icon = 'layer-group';

    protected string $orderBy = 'sort';

    public static function natures(): array
    {
        return [
            'asset' => __('Something we have, or someone who owes us'),
            'liability' => __('Someone we owe'),
            'equity' => __('Owners\' money'),
            'income' => __('Income'),
            'expense' => __('Expense'),
        ];
    }

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:100']],
            ['name' => 'nature', 'label' => 'What is it?', 'type' => 'select', 'options' => self::natures(), 'rules' => ['required', 'in:asset,liability,equity,income,expense']],
            ['name' => 'is_party', 'label' => 'A person or company (has phone and address)', 'type' => 'checkbox'],
            ['name' => 'is_money', 'label' => 'Holds money (we can pay from it)', 'type' => 'checkbox'],
        ];
    }

    protected function columns(): array
    {
        return [
            'Name' => fn (AccountType $t) => $t->name,
            'What is it?' => fn (AccountType $t) => self::natures()[$t->nature] ?? $t->nature,
            'Accounts' => fn (AccountType $t) => $t->accounts()->count(),
        ];
    }

    /** The kinds that come with the system are needed by its screens and stay as they are. */
    protected function canEdit(Model $record): bool
    {
        return str_starts_with($record->key, 'custom_');
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        if (! $record) {
            $data['key'] = 'custom_'.Str::slug($data['name'], '_').'_'.Str::lower(Str::random(4));
            $data['sort'] = 200;
        }

        return $data;
    }
}
