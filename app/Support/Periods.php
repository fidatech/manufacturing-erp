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

    /**
     * The last months up to this one, oldest first, in the calendar the user reads.
     *
     * @return list<array{label: string, from: string, to: string}>
     */
    public static function months(int $count): array
    {
        $today = today_iso();
        $shamsi = calendar() === 'shamsi';
        $names = $shamsi
            ? [__('Hamal'), __('Sawr'), __('Jawza'), __('Saratan'), __('Asad'), __('Sunbula'), __('Mizan'), __('Aqrab'), __('Qaws'), __('Jadi'), __('Dalw'), __('Hoot')]
            : [__('Jan'), __('Feb'), __('Mar'), __('Apr'), __('May'), __('Jun'), __('Jul'), __('Aug'), __('Sep'), __('Oct'), __('Nov'), __('Dec')];

        [$y, $m, $d] = array_map('intval', explode('-', $today));
        if ($shamsi) {
            [$y, $m] = Shamsi::fromGregorian($y, $m, $d);
        }
        $start = fn (int $year, int $month) => $shamsi
            ? vsprintf('%04d-%02d-%02d', Shamsi::toGregorian($year, $month, 1))
            : sprintf('%04d-%02d-01', $year, $month);

        $months = [];
        $end = $today;
        for ($i = 0; $i < $count; $i++) {
            $from = $start($y, $m);
            array_unshift($months, ['label' => $names[$m - 1], 'from' => $from, 'to' => $end]);
            $end = Carbon::parse($from)->subDay()->toDateString();
            [$y, $m] = $m === 1 ? [$y - 1, 12] : [$y, $m - 1];
        }

        return $months;
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
