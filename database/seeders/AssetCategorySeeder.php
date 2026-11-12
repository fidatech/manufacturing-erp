<?php

namespace Database\Seeders;

use App\Models\Lookup;
use Illuminate\Database\Seeder;

class AssetCategorySeeder extends Seeder
{
    public const CATEGORIES = [
        'Land and buildings', 'Building improvements', 'Machinery and equipment', 'Laboratory equipment', 'Power (generator, solar, stabilizer)',
        'Vehicles', 'Furniture and fittings', 'Office equipment', 'Computers and printers', 'Tools', 'Licences and software',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name) {
            Lookup::firstOrCreate(['type' => 'asset_category', 'parent_id' => null, 'name' => $name]);
        }
    }
}
