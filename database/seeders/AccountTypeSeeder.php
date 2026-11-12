<?php

namespace Database\Seeders;

use App\Models\AccountType;
use Illuminate\Database\Seeder;

/** The kinds of account the screens are built on. */
class AccountTypeSeeder extends Seeder
{
    /** key, name, nature, is a person or company, holds money, made by the system only, order */
    public const TYPES = [
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

    public function run(): void
    {
        foreach (self::TYPES as [$key, $name, $nature, $party, $money, $system, $sort]) {
            AccountType::firstOrCreate(['key' => $key], [
                'name' => $name, 'nature' => $nature, 'is_party' => $party, 'is_money' => $money, 'is_system' => $system, 'sort' => $sort,
            ]);
        }
    }
}
