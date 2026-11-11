<?php

namespace App\Services;

use App\Exceptions\UserError;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Support\Num;

/**
 * Exchange rates. A rate says how many units of a currency equal 1 unit of the
 * main currency (for example 1 USD = 70.50 AFN). The newest rate on or before
 * the date of a paper is used.
 */
class Rates
{
    private static ?Currency $base = null;

    public static function base(): Currency
    {
        return self::$base ??= Currency::where('is_base', true)->firstOrFail();
    }

    public static function forget(): void
    {
        self::$base = null;
    }

    public static function rate(int $currencyId, string $date): string
    {
        if ($currencyId === self::base()->id) {
            return '1';
        }

        $rate = ExchangeRate::where('currency_id', $currencyId)
            ->whereDate('date', '<=', $date)
            ->orderByDesc('date')
            ->value('rate');

        if ($rate === null || ! Num::isPositive($rate)) {
            $code = Currency::find($currencyId)?->code ?? '?';

            throw new UserError(__('app.errors.no_rate', ['currency' => $code, 'base' => self::base()->code]));
        }

        return (string) $rate;
    }

    /** Amount in a currency, expressed in the main currency. */
    public static function toBase(mixed $amount, int $currencyId, string $date, ?string $rate = null): string
    {
        return Num::money(Num::div($amount, $rate ?? self::rate($currencyId, $date)));
    }

    /** Amount in the main currency, expressed in another currency. */
    public static function fromBase(mixed $amount, int $currencyId, string $date, ?string $rate = null): string
    {
        return Num::money(Num::mul($amount, $rate ?? self::rate($currencyId, $date)));
    }
}
