<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;

/** Every permission of config/permissions.php (module.view and module.manage). */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        Permissions::sync();
    }
}
