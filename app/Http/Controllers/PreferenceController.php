<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    /** Choose the language. It is remembered for the user, and for the sign-in page of this browser. */
    public function locale(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'in:'.implode(',', array_keys(config('app.locales')))]]);

        $request->session()->put('locale', $data['locale']);
        if ($user = $request->user()) {
            $user->locale = $data['locale'];
            $user->saveQuietly();
        }

        return back();
    }

    /** Switch between the Gregorian and the Solar Hijri calendar. Only what is shown changes. */
    public function calendar(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->calendar = $user->calendar === 'shamsi' ? 'gregorian' : 'shamsi';
        $user->saveQuietly();

        return back();
    }
}
