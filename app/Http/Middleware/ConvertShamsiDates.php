<?php

namespace App\Http\Middleware;

use App\Support\Shamsi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users who chose the Solar Hijri calendar type dates like 1405/08/05.
 * Every date box they fill in is listed in the "_shamsi" field; those values are
 * turned into Gregorian here, before anything else sees them. The database and all
 * calculations only ever work with Gregorian dates.
 */
class ConvertShamsiDates
{
    public function handle(Request $request, Closure $next): Response
    {
        $fields = (array) $request->input('_shamsi', []);

        if ($fields) {
            $data = $request->all();
            foreach ($fields as $field) {
                $value = data_get($data, $field);
                if (is_string($value) && trim($value) !== '') {
                    // an impossible date stays wrong on purpose, so validation rejects it
                    data_set($data, $field, Shamsi::parse($value) ?? 'invalid-date');
                }
            }
            unset($data['_shamsi']);
            $request->replace($data);
        }

        return $next($request);
    }
}
