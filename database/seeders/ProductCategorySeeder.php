<?php

namespace Database\Seeders;

use App\Models\ItemCategory;
use Illuminate\Database\Seeder;

/** Groups for products and for raw materials. */
class ProductCategorySeeder extends Seeder
{
    public const CATEGORIES = [
        'product' => ['Finished products', 'Semi-finished products', 'Trading goods'],
        'raw' => ['Raw materials', 'Packing materials', 'Consumables', 'Spare parts'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $type => $names) {
            foreach ($names as $name) {
                ItemCategory::firstOrCreate(['name' => $name, 'type' => $type]);
            }
        }
    }
}
