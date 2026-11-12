<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\MakesAccounts;
use Illuminate\Database\Seeder;

/** Everyday expense categories (Finance > Expense). */
class ExpenseCategorySeeder extends Seeder
{
    use MakesAccounts;

    public const CATEGORIES = [
        'Rent', 'Electricity', 'Fuel and generator', 'Water', 'Transport and delivery', 'Loading and labour',
        'Repair and maintenance', 'Office supplies', 'Phone and internet', 'Food and hospitality', 'Travel',
        'Marketing and advertising', 'Taxes and licences', 'Bank and sarafi charges', 'Customs and clearance',
        'Laboratory supplies', 'Cleaning and security', 'Other expenses',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name) {
            $this->account('expense', $name);
        }
    }
}
