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

    protected array $with = ['parent'];

    /** type => [title, singular, icon, permission] */
    public const LISTS = [
        'department' => ['Departments', 'Department', 'building-user', 'hr'],
        'designation' => ['Designations', 'Designation', 'user-tag', 'hr'],
        'document_type' => ['Document Types', 'Document Type', 'folder-tree', 'documents'],
        'asset_category' => ['Asset Categories', 'Asset Category', 'layer-group', 'setup'],
        'qc_parameter' => ['Test Parameters', 'Test Parameter', 'list-check', 'lab'],
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
        $kind = $this->kind();
        $unique = Rule::unique('lookups')->where('type', $kind)->ignore($record?->id);
        $fields = [];

        // a designation belongs to a department
        if ($kind === 'designation') {
            $fields[] = ['name' => 'parent_id', 'label' => 'Department', 'type' => 'select', 'rules' => ['required', Rule::exists('lookups', 'id')->where('type', 'department')],
                'options' => Lookup::where('type', 'department')->orderBy('name')->pluck('name', 'id')->all(), 'help' => 'Add the department first if it is not in the list.'];
            $unique = $unique->where('parent_id', request('parent_id'));
        }
        $fields[] = ['name' => 'name', 'label' => 'Name', 'rules' => ['required', 'string', 'max:150', $unique]];

        return $fields;
    }

    protected function columns(): array
    {
        $columns = ['Name' => fn (Lookup $lookup) => $lookup->name];
        if ($this->kind() === 'designation') {
            $columns['Department'] = fn (Lookup $lookup) => $lookup->parent?->name;
        }
        if ($this->kind() === 'department') {
            $columns['Designations'] = fn (Lookup $lookup) => Lookup::where('type', 'designation')->where('parent_id', $lookup->id)->count();
        }

        return $columns;
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        return $data + ['type' => $this->kind()];
    }
}
