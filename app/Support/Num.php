<?php

namespace App\Support;

/**
 * Exact decimal arithmetic on strings (bcmath). Money and quantities never touch floats.
 */
final class Num
{
    private const WORK = 12;

    /** Turn user input or a database value into a clean decimal string. */
    public static function clean(mixed $value): string
    {
        $value = trim(str_replace([',', ' '], '', (string) ($value ?? '')));

        if ($value === '' || ! preg_match('/^-?\d*\.?\d*$/', $value) || $value === '-' || $value === '.') {
            return '0';
        }
        if (str_starts_with($value, '.')) {
            $value = '0'.$value;
        }
        if (str_starts_with($value, '-.')) {
            $value = '-0'.substr($value, 1);
        }

        return rtrim($value, '.');
    }

    public static function add(mixed $a, mixed $b): string
    {
        return bcadd(self::clean($a), self::clean($b), self::WORK);
    }

    public static function sub(mixed $a, mixed $b): string
    {
        return bcsub(self::clean($a), self::clean($b), self::WORK);
    }

    public static function mul(mixed $a, mixed $b): string
    {
        return bcmul(self::clean($a), self::clean($b), self::WORK);
    }

    /** Division; dividing by zero gives zero instead of an error. */
    public static function div(mixed $a, mixed $b): string
    {
        return self::isZero($b) ? '0' : bcdiv(self::clean($a), self::clean($b), self::WORK);
    }

    public static function cmp(mixed $a, mixed $b): int
    {
        return bccomp(self::clean($a), self::clean($b), self::WORK);
    }

    public static function isZero(mixed $a): bool
    {
        return self::cmp($a, '0') === 0;
    }

    public static function isPositive(mixed $a): bool
    {
        return self::cmp($a, '0') === 1;
    }

    public static function isNegative(mixed $a): bool
    {
        return self::cmp($a, '0') === -1;
    }

    public static function neg(mixed $a): string
    {
        return self::sub('0', $a);
    }

    public static function abs(mixed $a): string
    {
        return self::isNegative($a) ? self::neg($a) : self::clean($a);
    }

    /** Round half up (away from zero) to the given number of decimals. */
    public static function round(mixed $a, int $decimals = 2): string
    {
        $a = self::clean($a);
        $half = '0.'.str_repeat('0', $decimals).'5';

        return self::isNegative($a)
            ? bcsub($a, $half, $decimals)
            : bcadd($a, $half, $decimals);
    }

    /** Money: two decimals. */
    public static function money(mixed $a): string
    {
        return self::round($a, 2);
    }

    /** Quantities: four decimals. */
    public static function qty(mixed $a): string
    {
        return self::round($a, 4);
    }

    public static function sum(iterable $values): string
    {
        $total = '0';
        foreach ($values as $value) {
            $total = self::add($total, $value);
        }

        return $total;
    }

    /** For screens: 1,234.50 and no useless zeros on quantities. */
    public static function format(mixed $a, int $decimals = 2, bool $trim = false): string
    {
        $a = self::round($a, $decimals);
        $negative = str_starts_with($a, '-');
        [$int, $frac] = array_pad(explode('.', ltrim($a, '-')), 2, '');
        $int = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $int);
        if ($trim) {
            $frac = rtrim($frac, '0');
        }

        return ($negative && (trim($int.$frac, '0,') !== '') ? '-' : '').$int.($frac !== '' ? '.'.$frac : '');
    }
}
