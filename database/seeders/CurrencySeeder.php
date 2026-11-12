<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

/** US dollar (the main currency), afghani and Pakistani rupee. */
class CurrencySeeder extends Seeder
{
    public const CURRENCIES = [
        ['USD', 'US Dollar', '$'],
        ['AFN', 'Afghani', '؋'],
        ['PKR', 'Pakistani Rupee', '₨'],
    ];

    public function run(): void
    {
        // the first currency of the list becomes the main one, unless a main currency is already chosen
        $hasBase = Currency::where('is_base', true)->exists();

        foreach (self::CURRENCIES as $index => [$code, $name, $symbol]) {
            Currency::firstOrCreate(['code' => $code], [
                'name' => $name,
                'symbol' => $symbol,
                'is_base' => ! $hasBase && $index === 0,
                'is_active' => true,
            ]);
        }
    }
}
