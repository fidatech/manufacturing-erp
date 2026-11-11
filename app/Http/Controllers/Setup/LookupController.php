<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\CrudController;
use App\Models\Lookup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Short lists of names: /lists/department, /lists/designation, /lists/document_type, /lists/asset_category. */
class LookupController extends CrudController
{
    protected string $model = Lookup::class;

    protected string $route = 'lookups';

    protected string $permission = 'setup';

    protected string $title = 'List';

    protected string $singular = 'Name';

    /** type => [title, singular, icon, permission] */
    public const LISTS = [
        'department' => ['Departments', 'Department', 'building-user', 'hr'],
        'designation' => ['Designations', 'Designation', 'user-tag', 'hr'],
        'document_type' => ['Document Types', 'Document Type', 'folder-tree', 'documents'],
        'asset_category' => ['Asset Categories', 'Asset Category', 'layer-group', 'setup'],
    ];

    protected function kind(): string
    {
        $kind = request()->route('type');
        abort_unless(isset(self::LISTS[$kind]), 404);
        [$this->title, $this->singular, $this->icon, $this->permission] = self::LISTS[$kind];

        return $kind;
    }

    public function callAction($method, $parameters)
    {
        $this->kind();

        return parent::callAction($method, $parameters);
    }

    protected function routeParams(): array
    {
        return ['type' => $this->kind()];
    }

    protected function query(Request $request): Builder
    {
        return Lookup::query()->where('type', $this->kind());
    }

    protected function fields(?Model $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150', Rule::unique('lookups')->where('type', $this->kind())->ignore($record?->id)]],
        ];
    }

    protected function columns(): array
    {
        return ['Name' => fn (Lookup $lookup) => $lookup->name];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        return $data + ['type' => $this->kind()];
    }
}
