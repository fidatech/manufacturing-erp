<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public const UNITS = [
        ['Kilogram', 'kg'], ['Gram', 'g'], ['Ton', 't'], ['Liter', 'L'], ['Milliliter', 'ml'], ['Meter', 'm'],
        ['Piece', 'pc'], ['Dozen', 'dz'], ['Can', 'can'], ['Bottle', 'btl'], ['Bag', 'bag'], ['Drum', 'drum'],
        ['Carton', 'ctn'], ['Box', 'box'], ['Pack', 'pack'], ['Roll', 'roll'],
    ];

    public function run(): void
    {
        foreach (self::UNITS as [$name, $symbol]) {
            Unit::firstOrCreate(['name' => $name], ['symbol' => $symbol]);
        }
    }
}
