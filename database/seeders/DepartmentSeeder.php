<?php

namespace Database\Seeders;

use App\Models\Lookup;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public const DEPARTMENTS = ['Management', 'Finance', 'Sales', 'Purchasing', 'Production', 'Laboratory and Quality Control', 'Warehouse', 'Human Resources', 'Security and Services'];

    public function run(): void
    {
        foreach (self::DEPARTMENTS as $name) {
            Lookup::firstOrCreate(['type' => 'department', 'parent_id' => null, 'name' => $name]);
        }
    }
}
