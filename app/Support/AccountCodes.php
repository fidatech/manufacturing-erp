<?php

namespace App\Support;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Currency;

/**
 * Chart of accounts codes, given by the system:
 *
 *   1200-001-AFN   customer number 1, his account in afghani
 *   1200-001-USD   the same customer, his account in dollars
 *   5100-004       the fourth expense category
 *   4000           the system account "Sales"
 *
 * The first part says the kind of account (1 what we have, 2 what we owe, 3 owners, 4 income, 5 expenses).
 * One person or company keeps the same number in every currency; the currency is added at the end.
 */
class AccountCodes
{
    /** First part of the code for each kind of account. */
    public const TYPES = [
        'cash' => '1100', 'bank' => '1110', 'sarafi' => '1120', 'customer' => '1200', 'warehouse_manager' => '1250', 'asset' => '1300',
        'supplier' => '2100', 'employee' => '2200', 'liability' => '2300',
        'shareholder' => '3100', 'equity' => '3900',
        'income' => '4100',
        'expense' => '5100',
    ];

    /** Fixed codes of the accounts the system writes to itself. */
    public const SYSTEM = [
        'inventory' => '1310', 'current_assets' => '1320', 'fixed_assets' => '1510', 'intangible_assets' => '1520', 'accumulated_depreciation' => '1590',
        'tax_payable' => '2400',
        'profit_paid' => '3800', 'opening' => '3900',
        'sales' => '4000', 'other_income' => '4900',
        'cogs' => '5000', 'salaries' => '5200', 'depreciation' => '5300', 'stock_adjustment' => '5400', 'exchange_diff' => '5500',
        'production_cost' => '5600', 'dead_cash' => '5700',
    ];

    /** Kinds of account that can have one name in several currencies: the currency is part of the code. */
    private const BY_CURRENCY = ['cash', 'bank', 'sarafi', 'customer', 'warehouse_manager', 'supplier', 'employee', 'liability', 'shareholder'];

    /** The code a new account gets. */
    public static function for(Account $account): ?string
    {
        if ($account->system_key) {
            $code = self::SYSTEM[$account->system_key] ?? null;

            return $code && ! self::taken($code, $account) ? $code : null;
        }

        $typeKey = AccountType::whereKey($account->account_type_id)->value('key');
        $prefix = self::TYPES[$typeKey] ?? null;
        if (! $prefix) {
            return null;
        }
        $currency = in_array($typeKey, self::BY_CURRENCY, true) ? Currency::whereKey($account->currency_id)->value('code') : null;

        // the same name of the same kind keeps its number: one customer, one number, every currency.
        // "Cash USD" and "Cash AFN" are one name too: a currency at the end of a name does not count.
        $number = null;
        $holder = self::holder((string) $account->name);
        $others = Account::where('account_type_id', $account->account_type_id)->whereNotNull('code')->where('code', 'like', $prefix.'-%')
            ->when($account->exists, fn ($q) => $q->whereKeyNot($account->id))->get(['name', 'code']);
        foreach ($others as $other) {
            if (self::holder($other->name) === $holder && preg_match('/^'.$prefix.'-(\d+)/', $other->code, $found)) {
                $number = (int) $found[1];
                break;
            }
        }

        for ($try = 0; $try < 1000; $try++) {
            $number ??= self::nextNumber($prefix);
            $code = $prefix.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT).($currency ? '-'.$currency : '');
            if (! self::taken($code, $account)) {
                return $code;
            }
            // two accounts with one name and one currency: the second gets a number of its own
            $number = self::nextNumber($prefix);
        }

        return null;
    }

    /** A name without the currency at its end, in small letters: "Cash USD" becomes "cash". */
    private static function holder(string $name): string
    {
        static $codes = null;
        $codes ??= Currency::pluck('code')->map(fn ($code) => preg_quote($code, '/'))->implode('|');
        $plain = $codes === '' ? $name : preg_replace('/[\s\-(]*\b('.$codes.')\)?\s*$/iu', '', $name);

        return mb_strtolower(trim($plain !== '' ? $plain : $name));
    }

    private static function nextNumber(string $prefix): int
    {
        $highest = 0;
        foreach (Account::where('code', 'like', $prefix.'-%')->pluck('code') as $code) {
            if (preg_match('/^'.$prefix.'-(\d+)/', $code, $found)) {
                $highest = max($highest, (int) $found[1]);
            }
        }

        return $highest + 1;
    }

    private static function taken(string $code, Account $account): bool
    {
        return Account::where('code', $code)->when($account->exists, fn ($q) => $q->whereKeyNot($account->id))->exists();
    }
}
