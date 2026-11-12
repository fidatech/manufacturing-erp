<?php

use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\Num;
use App\Support\Shamsi;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('asset_v')) {
    /**
     * URL of a file in public/ with its modification time appended, so browsers can
     * cache it for a year and still pick up a new version the moment it changes.
     */
    function asset_v(string $path): string
    {
        static $urls = [];

        return $urls[$path] ??= asset($path).'?v='.(is_file(public_path($path)) ? filemtime(public_path($path)) : '0');
    }
}

if (! function_exists('text_dir')) {
    /** Text direction of the current language: "rtl" for Dari, Pashto and Arabic. */
    function text_dir(): string
    {
        return in_array(app()->getLocale(), ['fa', 'ps', 'ar'], true) ? 'rtl' : 'ltr';
    }
}

if (! function_exists('calendar')) {
    /** The calendar the signed-in user chose: "gregorian" or "shamsi". Storage is always Gregorian. */
    function calendar(): string
    {
        return auth()->user()?->calendar === 'shamsi' ? 'shamsi' : 'gregorian';
    }
}

if (! function_exists('fmt_date')) {
    /** A stored date as the user wants to see it: 27/10/2026 or 1405/08/05. */
    function fmt_date(mixed $date): string
    {
        if (! $date) {
            return '';
        }
        $iso = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);

        return calendar() === 'shamsi' ? Shamsi::format($iso) : implode('/', array_reverse(explode('-', $iso)));
    }
}

if (! function_exists('input_date')) {
    /** A stored date as the value of a date box. */
    function input_date(mixed $date): string
    {
        if (! $date) {
            return '';
        }
        $iso = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);

        return calendar() === 'shamsi' ? Shamsi::format($iso) : $iso;
    }
}

if (! function_exists('today_iso')) {
    function today_iso(): string
    {
        return now()->toDateString();
    }
}

if (! function_exists('money')) {
    /** 1234.5 becomes "1,234.50"; with a currency code "1,234.50 USD". */
    function money(mixed $amount, ?string $currency = null): string
    {
        return Num::format($amount, 2).($currency ? ' '.$currency : '');
    }
}

if (! function_exists('qty')) {
    /** 15.0000 becomes "15", 0.0250 becomes "0.025". */
    function qty(mixed $amount): string
    {
        return Num::format($amount, 4, true);
    }
}

if (! function_exists('setting')) {
    /** A company setting (name, phone, address ...). */
    function setting(string $key, ?string $default = null): ?string
    {
        if (! app()->bound('erp.settings')) {
            app()->instance('erp.settings', Setting::where('key', 'not like', 'seq.%')->pluck('value', 'key')->all());
        }
        $all = app('erp.settings');

        return $all[$key] ?? $default;
    }
}

if (! function_exists('audit')) {
    function audit(string $action, ?Model $subject = null, ?string $description = null): void
    {
        AuditLog::record($action, $subject, $description);
    }
}

if (! function_exists('quick_add')) {
    /**
     * Attributes that let a pick list (or a button) add a new record in a pop-up without leaving the page:
     *   <select name="supplier_id" {{ quick_add('accounts', __('New supplier'), 'accounts.create', ['type' => 'supplier']) }}>
     * Nothing is written when the user may not add such records.
     */
    function quick_add(string $permission, string $title, string $route, array $params = []): \Illuminate\Support\HtmlString
    {
        if (! auth()->user()?->can($permission.'.manage')) {
            return new \Illuminate\Support\HtmlString('');
        }

        return new \Illuminate\Support\HtmlString('data-quick-add="'.e(route($route, $params)).'" data-quick-title="'.e($title).'"');
    }
}
