<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Records the system cannot work without: currencies, kinds of account,
 * the accounts used behind the scenes, a cash box per currency and common units.
 * No customers, suppliers, products or amounts are created.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([['USD', 'US Dollar', '$', true], ['AFN', 'Afghani', '؋', false], ['PKR', 'Pakistani Rupee', 'Rs', false]] as [$code, $name, $symbol, $base]) {
            DB::table('currencies')->insert(['code' => $code, 'name' => $name, 'symbol' => $symbol, 'is_base' => $base, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        $currency = DB::table('currencies')->pluck('id', 'code');

        // key, name, nature, is_party, is_money, is_system, sort
        $types = [
            ['cash', 'Cash', 'asset', false, true, false, 10],
            ['bank', 'Bank', 'asset', false, true, false, 20],
            ['sarafi', 'Sarafi', 'asset', true, true, false, 30],
            ['customer', 'Customer', 'asset', true, false, false, 40],
            ['supplier', 'Supplier', 'liability', true, false, false, 50],
            ['employee', 'Employee', 'liability', true, false, false, 60],
            ['warehouse_manager', 'Warehouse Manager', 'asset', true, false, false, 70],
            ['shareholder', 'Shareholder', 'equity', true, false, false, 80],
            ['liability', 'Loan / Liability', 'liability', true, false, false, 90],
            ['income', 'Income', 'income', false, false, false, 100],
            ['expense', 'Expense', 'expense', false, false, false, 110],
            ['asset', 'Asset', 'asset', false, false, true, 120],
            ['equity', 'Equity', 'equity', false, false, true, 130],
        ];
        foreach ($types as [$key, $name, $nature, $party, $money, $system, $sort]) {
            DB::table('account_types')->insert(['key' => $key, 'name' => $name, 'nature' => $nature, 'is_party' => $party, 'is_money' => $money, 'is_system' => $system, 'sort' => $sort, 'created_at' => $now, 'updated_at' => $now]);
        }
        $type = DB::table('account_types')->pluck('id', 'key');

        // system_key, name, type
        $accounts = [
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
        ];
        foreach ($accounts as [$key, $name, $typeKey]) {
            DB::table('accounts')->insert(['account_type_id' => $type[$typeKey], 'name' => $name, 'currency_id' => $currency['USD'], 'system_key' => $key, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach (['USD', 'AFN', 'PKR'] as $code) {
            DB::table('accounts')->insert(['account_type_id' => $type['cash'], 'name' => "Cash $code", 'currency_id' => $currency[$code], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach ([['Kilogram', 'kg'], ['Liter', 'L'], ['Piece', 'pc'], ['Can', 'can'], ['Bag', 'bag'], ['Drum', 'drum'], ['Carton', 'ctn']] as [$name, $symbol]) {
            DB::table('units')->insert(['name' => $name, 'symbol' => $symbol, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        //
    }
};
