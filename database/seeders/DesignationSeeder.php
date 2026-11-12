<?php

namespace Database\Seeders;

use App\Models\Lookup;
use Illuminate\Database\Seeder;

/** Job titles, each under its department. */
class DesignationSeeder extends Seeder
{
    public const DESIGNATIONS = [
        'Management' => ['General Manager', 'Deputy Manager'],
        'Finance' => ['Finance Manager', 'Accountant', 'Cashier'],
        'Sales' => ['Sales Manager', 'Sales Officer'],
        'Purchasing' => ['Purchase Officer'],
        'Production' => ['Production Manager', 'Production Supervisor', 'Machine Operator', 'Technician', 'Worker'],
        'Laboratory and Quality Control' => ['Laboratory Manager', 'Laboratory Technician'],
        'Warehouse' => ['Warehouse Manager', 'Storekeeper', 'Loader'],
        'Human Resources' => ['HR Officer'],
        'Security and Services' => ['Guard', 'Driver', 'Cleaner', 'Cook'],
    ];

    public function run(): void
    {
        foreach (self::DESIGNATIONS as $department => $titles) {
            $parent = Lookup::firstOrCreate(['type' => 'department', 'parent_id' => null, 'name' => $department]);
            foreach ($titles as $title) {
                Lookup::firstOrCreate(['type' => 'designation', 'parent_id' => $parent->id, 'name' => $title]);
            }
        }
    }
}
