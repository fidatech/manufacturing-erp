<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The usual jobs in a factory office. The Administrator may do everything.
 * A role that already exists keeps the permissions it has now.
 */
class RoleSeeder extends Seeder
{
    /** role => modules it may change; the modules after "view" it may only look at */
    public const ROLES = [
        'Accountant' => ['manage' => ['accounts', 'money', 'journal', 'assets', 'purchases', 'sales', 'documents'], 'view' => ['reports', 'inventory', 'items', 'hr', 'production']],
        'Sales Officer' => ['manage' => ['sales'], 'view' => ['accounts', 'inventory', 'items', 'documents']],
        'Purchase Officer' => ['manage' => ['purchases'], 'view' => ['accounts', 'inventory', 'items', 'documents']],
        'Storekeeper' => ['manage' => ['inventory'], 'view' => ['items', 'purchases', 'sales', 'production']],
        'Production Supervisor' => ['manage' => ['production'], 'view' => ['inventory', 'items', 'lab']],
        'Laboratory Officer' => ['manage' => ['lab'], 'view' => ['production', 'inventory', 'items']],
        'HR Officer' => ['manage' => ['hr'], 'view' => ['documents']],
        'Viewer' => ['manage' => [], 'view' => ['accounts', 'money', 'journal', 'assets', 'purchases', 'sales', 'inventory', 'items', 'production', 'lab', 'hr', 'reports', 'documents']],
    ];

    public function run(): void
    {
        Permissions::sync();
        $known = Permissions::all();

        Role::findOrCreate(User::ADMIN_ROLE, 'web');

        foreach (self::ROLES as $name => $modules) {
            if (Role::where('name', $name)->where('guard_name', 'web')->exists()) {
                continue;
            }
            $permissions = [];
            foreach ($modules['manage'] as $module) {
                $permissions[] = $module.'.view';
                $permissions[] = $module.'.manage';
            }
            foreach ($modules['view'] as $module) {
                $permissions[] = $module.'.view';
            }
            Role::create(['name' => $name, 'guard_name' => 'web'])->syncPermissions(array_values(array_intersect($permissions, $known)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
