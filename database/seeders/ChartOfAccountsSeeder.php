<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\MakesAccounts;
use Illuminate\Database\Seeder;

/** The accounts the system itself writes to (stock, sales, cost of goods sold ...). */
class ChartOfAccountsSeeder extends Seeder
{
    use MakesAccounts;

    /** system key, name, kind */
    public const ACCOUNTS = [
        ['inventory', 'Stock (inventory)', 'asset'],
        ['fixed_assets', 'Fixed assets', 'asset'],
        ['intangible_assets', 'Intangible assets', 'asset'],
        ['current_assets', 'Other current assets', 'asset'],
        ['accumulated_depreciation', 'Depreciation so far', 'asset'],
        ['tax_payable', 'Salary tax to pay', 'liability'],
        ['opening', 'Opening balances', 'equity'],
        ['profit_paid', 'Profit paid to shareholders', 'equity'],
        ['sales', 'Sales', 'income'],
        ['other_income', 'Other income', 'income'],
        ['cogs', 'Cost of goods sold', 'expense'],
        ['salaries', 'Salaries', 'expense'],
        ['depreciation', 'Depreciation', 'expense'],
        ['stock_adjustment', 'Stock loss or gain', 'expense'],
        ['exchange_diff', 'Exchange rate difference', 'expense'],
        ['production_cost', 'Production cost', 'expense'],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as [$key, $name, $type]) {
            $this->account($type, $name, $key);
        }
    }
}
