<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Company name, logo, phone and address as they appear on screen and on printed bills. */
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

        return view('setup.company', ['fields' => self::FIELDS, 'hasLogo' => (bool) self::logoPath()]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('setup.manage');

        $data = $request->validate(array_fill_keys(array_keys(self::FIELDS), ['nullable', 'string', 'max:500']) + [
            // a small picture keeps every page fast on a slow connection
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:300'],
            'remove_logo' => ['nullable'],
            'app_font' => ['nullable', Rule::in(array_keys(config('fonts')))],
        ]);

        foreach (array_keys(self::FIELDS) as $key) {
            Setting::updateOrCreate(['key' => $key], ['value' => $data[$key] ?? null]);
        }

        Setting::updateOrCreate(['key' => 'app_font'], ['value' => $data['app_font'] ?? null]);

        if ($request->boolean('remove_logo') || $request->hasFile('logo')) {
            if ($old = self::logoPath()) {
                Storage::disk('local')->delete($old);
            }
            Setting::updateOrCreate(['key' => 'company_logo'], ['value' => null]);
        }
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('company', 'local');
            Setting::updateOrCreate(['key' => 'company_logo'], ['value' => $path]);
        }
        app()->forgetInstance('erp.settings');
        audit('updated', null, 'Company settings');

        return back()->with('success', __('Saved.'));
    }

    /** The logo file, for the sidebar and the login page. Browsers keep it for a day. */
    public function logo(): Response
    {
        $path = self::logoPath();
        abort_unless($path, 404);

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($path) ?: 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public static function logoPath(): ?string
    {
        $path = Setting::where('key', 'company_logo')->value('value');

        return $path && Storage::disk('local')->exists($path) ? $path : null;
    }

    /** Address of the logo with a version, or null when the company has none. */
    public static function logoUrl(): ?string
    {
        $path = self::logoPath();

        return $path ? route('company.logo').'?v='.substr(md5($path), 0, 8) : null;
    }

    /** The logo inside the page itself, for printed papers. */
    public static function logoDataUri(): ?string
    {
        $path = self::logoPath();

        return $path ? 'data:'.(Storage::disk('local')->mimeType($path) ?: 'image/png').';base64,'.base64_encode(Storage::disk('local')->get($path)) : null;
    }
}
