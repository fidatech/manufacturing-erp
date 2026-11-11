<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CrudController;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends CrudController
{
    protected string $model = User::class;

    protected string $route = 'users';

    protected string $permission = 'users';

    protected string $title = 'Users';

    protected string $singular = 'User';

    protected string $icon = 'users';

    protected array $search = ['name', 'email'];

    protected array $with = ['roles'];

    protected function fields(?Model $record): array
    {
        Permissions::sync();

        return [
            ['name' => 'name', 'label' => 'Full name', 'rules' => ['required', 'string', 'max:255']],
            ['name' => 'email', 'label' => 'Email address', 'type' => 'email', 'rules' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($record?->id)]],
            ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'rules' => [$record ? 'nullable' : 'required', Password::min(8)],
                'help' => $record ? 'Leave empty to keep the current password' : 'At least 8 characters'],
            ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'virtual' => true, 'value' => $record?->roles->first()?->name,
                'options' => Role::orderBy('name')->pluck('name', 'name')->all(), 'rules' => ['required', 'exists:roles,name']],
            ['name' => 'calendar', 'label' => 'Calendar', 'type' => 'select', 'default' => 'gregorian',
                'options' => ['gregorian' => __('Gregorian (27/10/2026)'), 'shamsi' => __('Solar Hijri (1405/08/05)')], 'rules' => ['required', 'in:gregorian,shamsi']],
            ['name' => 'is_active', 'label' => 'May sign in', 'type' => 'checkbox', 'default' => true],
        ];
    }

    protected function columns(): array
    {
        return [
            'Full name' => fn (User $u) => $u->name,
            'Email address' => fn (User $u) => $u->email,
            'Role' => fn (User $u) => $u->roles->pluck('name')->implode(', '),
            'May sign in' => fn (User $u) => $u->is_active ? __('Yes') : __('No'),
        ];
    }

    protected function beforeSave(array $data, ?Model $record, Request $request): array
    {
        // nobody can lock themselves out
        if ($record && $record->is($request->user())) {
            $data['is_active'] = true;
        }

        return $data;
    }

    protected function afterSave(Model $record, array $virtual, Request $request, bool $created): void
    {
        $keepAdmin = $record->is($request->user()) && $record->isAdmin();
        $record->syncRoles([$keepAdmin ? User::ADMIN_ROLE : $virtual['role']]);
    }

    protected function deleteProblem(Model $record): ?string
    {
        return $record->is(request()->user()) ? __('You cannot delete your own user.') : null;
    }
}
