<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Amount boxes show thousands with commas (12,500.75). Before anything else reads
 * the request, those commas are taken out, so every screen validates, calculates
 * and stores the plain number (12500.75).
 *
 * Only values that are exactly a comma-grouped number are touched, and never the
 * boxes that hold free text.
 */
class StripNumberCommas
{
    /** Boxes whose text is kept exactly as typed, even when it looks like a number. */
    private const TEXT_FIELDS = [
        'name', 'title', 'description', 'notes', 'note', 'address', 'delivery_address', 'phone', 'company', 'code',
        'batch_no', 'supplier_invoice_no', 'transport_no', 'driver', 'parameter', 'standard', 'result', 'tested_by',
        'email', 'password', 'q', 'category', 'packing',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $request->replace($this->clean($request->all()));

        return $next($request);
    }

    private function clean(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->clean($value);
            } elseif (is_string($value) && ! in_array($key, self::TEXT_FIELDS, true) && self::isGroupedNumber($value)) {
                $data[$key] = str_replace(',', '', trim($value));
            }
        }

        return $data;
    }

    /** "1,250", "12,500.75" and "-3,000,000" are grouped numbers; "1,25" and "a,b" are not. */
    public static function isGroupedNumber(string $value): bool
    {
        return preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', trim($value)) === 1;
    }
}
