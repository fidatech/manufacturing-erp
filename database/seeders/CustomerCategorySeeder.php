<?php

namespace Database\Seeders;

use App\Models\Lookup;
use Illuminate\Database\Seeder;

/** Usual groups of customers. Rename or add more under Accounts > Customer Categories. */
class CustomerCategorySeeder extends Seeder
{
    public const CATEGORIES = ['Wholesaler', 'Retailer', 'Distributor', 'Factory', 'Export'];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name) {
            Lookup::firstOrCreate(['type' => 'customer_category', 'parent_id' => null, 'name' => $name]);
        }
    }
}
