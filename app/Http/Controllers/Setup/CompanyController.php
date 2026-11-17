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
        'company_address_local' => 'Address (Dari / Pashto)',
        'invoice_note' => 'Note printed at the bottom of every bill',
    ];

    public function edit(): View
    {
        Gate::authorize('setup.view');

        return view('setup.company', ['fields' => self::FIELDS, 'hasLogo' => (bool) self::logoPath(), 'hasPrintLogo' => (bool) self::logoPath(self::PRINT_LOGO)]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('setup.manage');

        $data = $request->validate(array_fill_keys(array_keys(self::FIELDS), ['nullable', 'string', 'max:500']) + [
            // a small picture keeps every page fast on a slow connection
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:300'],
            'remove_logo' => ['nullable'],
            // a black and white logo for printed papers (receipts, bills, statements, reports)
            'print_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:300'],
            'remove_print_logo' => ['nullable'],
            'app_font' => ['nullable', Rule::in(array_keys(config('fonts')))],
        ]);

        foreach (array_keys(self::FIELDS) as $key) {
            Setting::updateOrCreate(['key' => $key], ['value' => $data[$key] ?? null]);
        }

        Setting::updateOrCreate(['key' => 'app_font'], ['value' => $data['app_font'] ?? null]);

        $this->keepLogo($request, 'logo', 'remove_logo', self::LOGO);
        $this->keepLogo($request, 'print_logo', 'remove_print_logo', self::PRINT_LOGO);
        app()->forgetInstance('erp.settings');
        audit('updated', null, 'Company settings');

        return back()->with('success', __('Saved.'));
    }

    /** Replace or remove one of the two logos. */
    private function keepLogo(Request $request, string $field, string $remove, string $key): void
    {
        if ($request->boolean($remove) || $request->hasFile($field)) {
            if ($old = self::logoPath($key)) {
                Storage::disk('local')->delete($old);
            }
            Setting::updateOrCreate(['key' => $key], ['value' => null]);
        }
        if ($request->hasFile($field)) {
            Setting::updateOrCreate(['key' => $key], ['value' => $request->file($field)->store('company', 'local')]);
        }
    }

    /** A logo file (?print=1 for the black and white one). Browsers keep it for a day. */
    public function logo(Request $request): Response
    {
        $path = $request->boolean('print') ? self::logoPath(self::PRINT_LOGO) : self::logoPath();
        abort_unless($path, 404);

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($path) ?: 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public const LOGO = 'company_logo';

    public const PRINT_LOGO = 'company_logo_print';

    public static function logoPath(string $key = self::LOGO): ?string
    {
        $path = Setting::where('key', $key)->value('value');

        return $path && Storage::disk('local')->exists($path) ? $path : null;
    }

    /** Address of the logo with a version, or null when the company has none. */
    public static function logoUrl(): ?string
    {
        $path = self::logoPath();

        return $path ? route('company.logo').'?v='.substr(md5($path), 0, 8) : null;
    }

    /** The logo for papers: the black and white one when there is one, otherwise the usual logo. */
    public static function printLogoPath(): ?string
    {
        return self::logoPath(self::PRINT_LOGO) ?? self::logoPath();
    }

    /** Address of the paper logo, for papers printed straight from a screen. */
    public static function printLogoUrl(): ?string
    {
        if ($path = self::logoPath(self::PRINT_LOGO)) {
            return route('company.logo', ['print' => 1]).'&v='.substr(md5($path), 0, 8);
        }

        return self::logoUrl();
    }

    /** The paper logo inside the page itself, for printed papers (receipts, bills, statements, reports). */
    public static function logoDataUri(): ?string
    {
        $path = self::printLogoPath();

        return $path ? 'data:'.(Storage::disk('local')->mimeType($path) ?: 'image/png').';base64,'.base64_encode(Storage::disk('local')->get($path)) : null;
    }
}
