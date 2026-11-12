<?php

namespace Database\Seeders\Concerns;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Currency;

trait MakesAccounts
{
    /** Make an account of a kind in the main currency, unless one with that name is already there. */
    protected function account(string $typeKey, string $name, ?string $systemKey = null, ?string $currency = null): Account
    {
        $typeId = AccountType::where('key', $typeKey)->value('id');
        $currencyId = $currency
            ? Currency::where('code', $currency)->value('id')
            : Currency::where('is_base', true)->value('id');

        if ($systemKey) {
            return Account::firstOrCreate(['system_key' => $systemKey], ['account_type_id' => $typeId, 'name' => $name, 'currency_id' => $currencyId, 'is_active' => true]);
        }

        return Account::firstOrCreate(['account_type_id' => $typeId, 'name' => $name, 'currency_id' => $currencyId], ['is_active' => true]);
    }
}
