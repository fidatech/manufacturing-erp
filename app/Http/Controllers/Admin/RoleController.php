<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\UserError;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('users.view');
        Permissions::sync();

        return view('admin.roles.index', ['roles' => Role::withCount('users')->orderBy('name')->get()]);
    }

    /** One table that shows what every role may do. */
    public function permissions(): View
    {
        Gate::authorize('users.view');
        Permissions::sync();
        $roles = Role::with('permissions')->orderBy('name')->get();

        return view('admin.permissions', [
            'roles' => $roles,
            'modules' => config('permissions'),
            'granted' => $roles->mapWithKeys(fn (Role $role) => [$role->id => $role->permissions->pluck('name')->all()])->all(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('users.manage');
        Permissions::sync();

        return view('admin.roles.form', ['role' => null, 'granted' => [], 'modules' => config('permissions')]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('users.manage');
        Permissions::sync();

        $data = $this->validated($request, null);
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);
        audit('created', $role, 'Role '.$role->name);

        return redirect()->route('roles.index')->with('success', __('Role saved.'));
    }

    public function edit(Request $request): View
    {
        Gate::authorize('users.manage');
        Permissions::sync();
        $role = $this->editable($request);

        return view('admin.roles.form', ['role' => $role, 'granted' => $role->permissions->pluck('name')->all(), 'modules' => config('permissions')]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('users.manage');
        $role = $this->editable($request);

        $data = $this->validated($request, $role);
        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);
        audit('updated', $role, 'Role '.$role->name);

        return redirect()->route('roles.index')->with('success', __('Role saved.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('users.manage');
        $role = $this->editable($request);

        if ($role->users()->exists()) {
            throw new UserError(__('This role is still given to users. Give them another role first.'));
        }
        audit('deleted', $role, 'Role '.$role->name);
        $role->delete();

        return redirect()->route('roles.index')->with('success', __('Role deleted.'));
    }

    private function editable(Request $request): Role
    {
        $role = Role::findOrFail($request->route('id'));
        abort_if($role->name === User::ADMIN_ROLE, 403);

        return $role;
    }

    private function validated(Request $request, ?Role $role): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles')->ignore($role?->id)],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permissions::all())],
        ]);
    }
}
