<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/** One warehouse to start with. Nothing is added when the company already has a warehouse. */
class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        if (! Warehouse::exists()) {
            Warehouse::create(['name' => 'Main Warehouse', 'is_active' => true]);
        }
    }
}
