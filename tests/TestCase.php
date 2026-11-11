<?php

namespace Tests;

use App\Models\Account;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    /** A signed-in administrator. */
    protected function admin(array $attributes = []): User
    {
        Permissions::sync();
        $user = User::factory()->create($attributes);
        $user->assignRole(User::ADMIN_ROLE);
        $this->actingAs($user);

        return $user;
    }

    /** A signed-in user whose role has only the given permissions. */
    protected function userWith(array $permissions): User
    {
        Permissions::sync();
        $role = Role::findOrCreate('Test role '.md5(implode(',', $permissions)), 'web');
        $role->syncPermissions($permissions);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    protected function currency(string $code): Currency
    {
        return Currency::where('code', $code)->firstOrFail();
    }

    /** 1 USD = $rate units of the currency, from the given date on. */
    protected function rate(string $code, string $rate, string $date = '2026-01-01'): void
    {
        ExchangeRate::create(['currency_id' => $this->currency($code)->id, 'date' => $date, 'rate' => $rate]);
    }

    protected function account(string $type, string $name, string $currency = 'USD', array $extra = []): Account
    {
        return Account::create([
            'account_type_id' => \App\Models\AccountType::where('key', $type)->value('id'),
            'name' => $name,
            'currency_id' => $this->currency($currency)->id,
        ] + $extra);
    }

    protected function cash(string $currency): Account
    {
        return Account::where('name', "Cash $currency")->firstOrFail();
    }
}
