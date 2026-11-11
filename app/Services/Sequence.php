<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Paper numbers: SI-000001, PI-000001 ... Call inside a database transaction. */
class Sequence
{
    public static function next(string $prefix): string
    {
        $key = 'seq.'.$prefix;
        $row = DB::table('settings')->where('key', $key)->lockForUpdate()->first();
        $next = $row ? ((int) $row->value) + 1 : 1;

        if ($row) {
            DB::table('settings')->where('key', $key)->update(['value' => (string) $next]);
        } else {
            DB::table('settings')->insert(['key' => $key, 'value' => (string) $next]);
        }

        return sprintf('%s-%06d', $prefix, $next);
    }
}
