<?php

namespace App\Support;

/**
 * Solar Hijri (Afghan) calendar. The database always stores Gregorian dates;
 * this class only converts for screens and for what the user types.
 */
final class Shamsi
{
    public const MONTHS = [
        1 => 'Hamal', 'Sawr', 'Jawza', 'Saratan', 'Asad', 'Sunbula',
        'Mizan', 'Aqrab', 'Qaws', 'Jadi', 'Dalw', 'Hoot',
    ];

    /** @return array{int,int,int} year, month, day */
    public static function fromGregorian(int $gy, int $gm, int $gd): array
    {
        $cumulative = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $cumulative[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            return [$jy, 1 + intdiv($days, 31), 1 + ($days % 31)];
        }

        return [$jy, 7 + intdiv($days - 186, 30), 1 + (($days - 186) % 30)];
    }

    /** @return array{int,int,int} year, month, day */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd + ($jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $leap = ($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0;
        $lengths = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        for ($gm = 0; $gm < 13 && $gd > $lengths[$gm]; $gm++) {
            $gd -= $lengths[$gm];
        }

        return [$gy, $gm, $gd];
    }

    /** "2026-10-27" becomes "1405/08/05". */
    public static function format(string $gregorian): string
    {
        [$y, $m, $d] = array_map('intval', explode('-', substr($gregorian, 0, 10)));
        [$jy, $jm, $jd] = self::fromGregorian($y, $m, $d);

        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }

    /** "1405/08/05" or "1405-8-5" becomes "2026-10-27"; null when it is not a real date. */
    public static function parse(?string $shamsi): ?string
    {
        $shamsi = strtr(trim((string) $shamsi), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
        if (! preg_match('/^(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})$/', $shamsi, $m)) {
            return null;
        }
        [$jy, $jm, $jd] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) {
            return null;
        }
        [$gy, $gm, $gd] = self::toGregorian($jy, $jm, $jd);
        // A day that does not exist (for example 31 Mizan) does not convert back to itself.
        if (self::fromGregorian($gy, $gm, $gd) !== [$jy, $jm, $jd]) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }
}
