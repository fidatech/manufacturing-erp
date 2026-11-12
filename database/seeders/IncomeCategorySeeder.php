<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\MakesAccounts;
use Illuminate\Database\Seeder;

/** Income other than sales of products (sales have their own system account). */
class IncomeCategorySeeder extends Seeder
{
    use MakesAccounts;

    public const CATEGORIES = ['Sale of scrap and empty packing', 'Service income', 'Transport charged to customers', 'Discount received', 'Commission income'];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name) {
            $this->account('income', $name);
        }
    }
}
