<?php

namespace App\Http\Controllers;

use App\Exceptions\UserError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * One list and one form for a simple record. A screen only declares its
 * fields and columns; listing, searching, saving and deleting are shared.
 *
 * Field: ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'rules' => ['nullable'], ...]
 *   type     text, email, password, number, decimal, select, textarea, date, checkbox
 *   options  [value => label] for select
 *   col      width on a 12-column row (default 6)
 *   meta     true: kept in the record's "meta" JSON column
 *   virtual  true: not saved on the record, only handed to afterSave()
 *   help     small grey text under the box
 */
abstract class CrudController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    protected string $route;

    protected string $permission;

    protected string $title;

    protected string $singular;

    protected string $icon = 'file';

    /** @var array<int, string> */
    protected array $search = ['name'];

    /** @var array<int, string> */
    protected array $with = [];

    protected string $orderBy = 'name';

    protected string $orderDirection = 'asc';

    /** @return array<int, array<string, mixed>> */
    abstract protected function fields(?Model $record): array;

    /** @return array<string, callable> column label => function (Model $record) */
    abstract protected function columns(): array;

    protected function query(Request $request): Builder
    {
        return $this->model::query();
    }

    /** Extra route parameters, for screens that live under /something/{type}. */
    protected function routeParams(): array
    {
        return [];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        return $data;
    }

    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        //
    }

    /** Return a reason to refuse deleting, or null to allow it. */
    protected function deleteProblem(Model $record): ?string
    {
        return null;
    }

    /** Extra links for a row: [['label' => .., 'url' => .., 'icon' => ..]] */
    protected function rowLinks(Model $record): array
    {
        return [];
    }

    protected function canEdit(Model $record): bool
    {
        return true;
    }

    public function index(Request $request): View
    {
        Gate::authorize($this->permission.'.view');

        $term = trim((string) $request->query('q', ''));
        $rows = $this->query($request)
            ->with($this->with)
            ->when($term !== '', function (Builder $query) use ($term) {
                $query->where(function (Builder $inner) use ($term) {
                    foreach ($this->search as $column) {
                        $inner->orWhere($column, 'like', '%'.$term.'%');
                    }
                });
            })
            ->orderBy($this->orderBy, $this->orderDirection)
            ->paginate(25)
            ->withQueryString();

        return view('crud.index', $this->shared() + [
            'rows' => $rows,
            'columns' => $this->columns(),
            'term' => $term,
            'filters' => $this->filters($request),
            'canManage' => $request->user()->can($this->permission.'.manage'),
            'links' => fn (Model $record) => $this->rowLinks($record),
            'editable' => fn (Model $record) => $this->canEdit($record),
        ]);
    }

    /**
     * Extra pick lists beside the search box of the list:
     * [['name' => 'currency', 'label' => 'Currency', 'options' => [value => text], 'value' => chosen], ...]
     * The screen applies them itself in query().
     */
    protected function filters(Request $request): array
    {
        return [];
    }

    public function create(Request $request): View
    {
        Gate::authorize($this->permission.'.manage');

        return view('crud.form', $this->shared() + [
            'record' => null,
            'fields' => $this->fields(null),
            'action' => route($this->route.'.store', $this->routeParams()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize($this->permission.'.manage');

        $this->save($request, null);

        return redirect()->route($this->route.'.index', $this->routeParams())->with('success', __(':name saved.', ['name' => __($this->singular)]));
    }

    public function edit(Request $request): View
    {
        Gate::authorize($this->permission.'.manage');

        $record = $this->find($request);
        abort_unless($this->canEdit($record), 403);

        return view('crud.form', $this->shared() + [
            'record' => $record,
            'fields' => $this->fields($record),
            'action' => route($this->route.'.update', $this->routeParams() + ['id' => $record->getKey()]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize($this->permission.'.manage');

        $record = $this->find($request);
        abort_unless($this->canEdit($record), 403);
        $this->save($request, $record);

        return redirect()->route($this->route.'.index', $this->routeParams())->with('success', __(':name saved.', ['name' => __($this->singular)]));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize($this->permission.'.manage');

        $record = $this->find($request);
        abort_unless($this->canEdit($record), 403);

        if ($problem = $this->deleteProblem($record)) {
            throw new UserError($problem);
        }

        try {
            DB::transaction(fn () => $record->delete());
        } catch (QueryException) {
            // something else still points at this record
            throw new UserError(__('This cannot be deleted because it is already used. Switch it off instead.'));
        }

        return redirect()->route($this->route.'.index', $this->routeParams())->with('success', __(':name deleted.', ['name' => __($this->singular)]));
    }

    protected function find(Request $request): Model
    {
        return $this->query($request)->findOrFail($request->route('id'));
    }

    protected function save(Request $request, ?Model $record): Model
    {
        $fields = array_values(array_filter($this->fields($record), fn (array $field) => empty($field['readonly'])));

        $rules = [];
        $labels = [];
        foreach ($fields as $field) {
            $rules[$field['name']] = $field['rules'] ?? ['nullable'];
            $labels[$field['name']] = mb_strtolower(__($field['label']));
        }
        $input = $request->validate($rules, [], $labels);

        $data = [];
        $meta = $record?->meta ?? [];
        $virtual = [];
        $hasMeta = false;
        foreach ($fields as $field) {
            $name = $field['name'];
            $value = ($field['type'] ?? 'text') === 'checkbox' ? $request->boolean($name) : ($input[$name] ?? null);

            if (! empty($field['virtual'])) {
                $virtual[$name] = $value;
            } elseif (! empty($field['meta'])) {
                $meta[$name] = $value;
                $hasMeta = true;
            } elseif (($field['type'] ?? 'text') === 'password' && ($value === null || $value === '')) {
                continue; // an empty password box keeps the old password
            } else {
                $data[$name] = $value;
            }
        }
        if ($hasMeta) {
            $data['meta'] = $meta;
        }

        return DB::transaction(function () use ($data, $virtual, $record, $request) {
            $data = $this->beforeSave($data, $record, $request);
            $created = $record === null;
            $record = $record ?? new $this->model;
            $record->fill($data)->save();
            $this->afterSave($record, $virtual, $request, $created);

            return $record;
        });
    }

    protected function shared(): array
    {
        return [
            'title' => __($this->title),
            'singular' => __($this->singular),
            'icon' => $this->icon,
            'route' => $this->route,
            'routeParams' => $this->routeParams(),
        ];
    }
}
