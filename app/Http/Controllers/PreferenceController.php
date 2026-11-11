<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    /** Switch between the Gregorian and the Solar Hijri calendar. Only what is shown changes. */
    public function calendar(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->calendar = $user->calendar === 'shamsi' ? 'gregorian' : 'shamsi';
        $user->saveQuietly();

        return back();
    }
}
