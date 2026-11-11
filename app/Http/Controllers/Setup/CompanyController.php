<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Company name, phone and address as they appear on printed bills. */
class CompanyController extends Controller
{
    public const FIELDS = [
        'company_name' => 'Company name',
        'company_name_local' => 'Company name (Dari / Pashto)',
        'company_phone' => 'Phone',
        'company_email' => 'Email',
        'company_address' => 'Address',
        'invoice_note' => 'Note printed at the bottom of every bill',
    ];

    public function edit(): View
    {
        Gate::authorize('setup.view');

        return view('setup.company', ['fields' => self::FIELDS]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('setup.manage');

        $data = $request->validate(array_fill_keys(array_keys(self::FIELDS), ['nullable', 'string', 'max:500']));
        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        audit('updated', null, 'Company settings');

        return back()->with('success', __('Saved.'));
    }
}
