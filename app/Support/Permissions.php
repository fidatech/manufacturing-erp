<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class Permissions
{
    /** @return array<int, string> every permission name: sales.view, sales.manage ... */
    public static function all(): array
    {
        $names = [];
        foreach (array_keys(config('permissions')) as $module) {
            $names[] = $module.'.view';
            $names[] = $module.'.manage';
        }

        return $names;
    }

    /** Make sure the database knows every permission and the Administrator role exists. */
    public static function sync(): void
    {
        foreach (self::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }
        Role::findOrCreate(User::ADMIN_ROLE, 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
