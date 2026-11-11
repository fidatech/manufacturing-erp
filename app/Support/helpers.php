<?php

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
