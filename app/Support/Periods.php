<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Ready-made date ranges for statements: this month, last month, this year, all time.
 * Months and years follow the calendar the user reads (Gregorian or Solar Hijri);
 * the dates returned are always Gregorian.
 */
class Periods
{
    /**
     * @param  string|null  $first  date of the oldest record, for "All time"
     * @return list<array{label: string, from: string, to: string, current: bool}>
     */
    public static function quick(string $from, string $to, ?string $first = null): array
    {
        $today = today_iso();
        [$monthStart, $lastMonthStart, $yearStart] = calendar() === 'shamsi' ? self::shamsiStarts($today) : self::gregorianStarts($today);
        $lastMonthEnd = Carbon::parse($monthStart)->subDay()->toDateString();
        $first = substr((string) ($first ?: $yearStart), 0, 10);

        $list = [
            [__('This month'), $monthStart, $today],
            [__('Last month'), $lastMonthStart, $lastMonthEnd],
            [__('This year'), $yearStart, $today],
            [__('All time'), min($first, $yearStart), $today],
        ];

        $found = false;

        return array_map(function (array $period) use ($from, $to, &$found) {
            $current = ! $found && $period[1] === $from && $period[2] === $to;
            $found = $found || $current;

            return ['label' => $period[0], 'from' => $period[1], 'to' => $period[2], 'current' => $current];
        }, $list);
    }

    /** @return array{string,string,string} start of this month, of last month, of this year */
    private static function gregorianStarts(string $today): array
    {
        $date = Carbon::parse($today);

        return [
            $date->copy()->startOfMonth()->toDateString(),
            $date->copy()->startOfMonth()->subMonthNoOverflow()->toDateString(),
            $date->copy()->startOfYear()->toDateString(),
        ];
    }

    /** @return array{string,string,string} */
    private static function shamsiStarts(string $today): array
    {
        [$y, $m, $d] = array_map('intval', explode('-', $today));
        [$jy, $jm] = Shamsi::fromGregorian($y, $m, $d);
        $iso = fn (int $year, int $month) => vsprintf('%04d-%02d-%02d', Shamsi::toGregorian($year, $month, 1));

        return [
            $iso($jy, $jm),
            $jm === 1 ? $iso($jy - 1, 12) : $iso($jy, $jm - 1),
            $iso($jy, 1),
        ];
    }
}
