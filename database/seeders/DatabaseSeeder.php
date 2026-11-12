<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The starting records every company needs. Safe to run more than once:
 * a record that is already there is left alone.
 *
 *   php artisan db:seed
 *
 * The real data of one client is a separate seeder, run only for that client:
 *
 *   php artisan db:seed --class="Database\Seeders\Clients\KanozaySeeder"
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            CurrencySeeder::class,
            UnitSeeder::class,
            AccountTypeSeeder::class,
            ChartOfAccountsSeeder::class,
            CashAccountSeeder::class,
            ExpenseCategorySeeder::class,
            IncomeCategorySeeder::class,
            AssetCategorySeeder::class,
            WarehouseSeeder::class,
            ProductCategorySeeder::class,
            DepartmentSeeder::class,
            DesignationSeeder::class,
        ]);
    }
}
