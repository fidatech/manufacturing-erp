<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows the system in the language the person chose: English, Dari or Pashto.
 * A signed-in user's own choice comes first, then the choice made on the sign-in page,
 * then the default of the system (APP_LOCALE). Dari and Pashto are written right to left.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $known = array_keys(config('app.locales'));
        $locale = $request->user()?->locale ?: ($request->hasSession() ? $request->session()->get('locale') : null);

        if (in_array($locale, $known, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
