<?php

namespace App\Support;

/**
 * Small helpers for the charts on the dashboard. The charts are plain HTML and CSS
 * (no chart library), so they cost almost nothing on a slow connection.
 * Floats are used here only to size the bars on the screen, never for money.
 */
class Chart
{
    /**
     * A round top for the value axis and the lines to draw: 8,340 becomes 10,000 with lines at 10K, 5K and 0.
     *
     * @param  list<mixed>  $values
     * @return array{max: string, ticks: list<string>}
     */
    public static function scale(array $values): array
    {
        $top = 0.0;
        foreach ($values as $value) {
            $top = max($top, (float) $value);
        }
        if ($top <= 0) {
            return ['max' => '1', 'ticks' => ['', '', '0']];
        }
        $power = 10 ** floor(log10($top));
        $max = $power * 10;
        foreach ([1, 2, 4, 5, 10] as $step) {
            if ($top <= $step * $power) {
                $max = $step * $power;
                break;
            }
        }

        return ['max' => Num::clean(number_format($max, 2, '.', '')), 'ticks' => [self::compact($max), self::compact($max / 2), '0']];
    }

    /** Height or width of a bar as a percentage of the top of the axis: "42.5". */
    public static function percent(mixed $value, mixed $max): string
    {
        if (! Num::isPositive($value) || ! Num::isPositive($max)) {
            return '0';
        }
        $percent = Num::round(Num::div(Num::mul($value, '100'), $max), 1);

        return Num::cmp($percent, '100') > 0 ? '100' : $percent;
    }

    /** 12500 becomes "12.5K", 3000000 becomes "3M", 750 stays "750". */
    public static function compact(float $value): string
    {
        $trim = fn (float $number) => rtrim(rtrim(number_format($number, 1, '.', ''), '0'), '.');

        return match (true) {
            $value >= 1_000_000 => $trim($value / 1_000_000).'M',
            $value >= 1_000 => $trim($value / 1_000).'K',
            default => $trim($value),
        };
    }
}
