<?php

namespace Database\Seeders;

use App\Models\Currency;
use Database\Seeders\Concerns\MakesAccounts;
use Illuminate\Database\Seeder;

/** One cash box for every currency: Cash USD, Cash AFN, Cash PKR. */
class CashAccountSeeder extends Seeder
{
    use MakesAccounts;

    public function run(): void
    {
        foreach (Currency::orderBy('id')->pluck('code') as $code) {
            $this->account('cash', 'Cash '.$code, null, $code);
        }
    }
}
