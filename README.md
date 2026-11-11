# Manufacturing ERP

A simple ERP for a manufacturing company, built by [Fida Technologies](https://fida.af).
Laravel, Blade, plain CSS and a little JavaScript. No React, Vue, Livewire or build tools.

**Designed for slow internet.** The interface is the fida-admin template, rebuilt so that
nothing is loaded from a CDN:

| What the browser downloads | Size (compressed) |
|---|---|
| CSS (Bootstrap + template, unused rules removed) | about 15 KB, once |
| JavaScript (no jQuery, no plugins) | about 4 KB, once |
| Icons (one SVG sprite with only the icons in use) | about 4 KB, once |
| Each page (HTML) | 2 to 5 KB |

CSS, JavaScript and icons are cached by the browser for a year, so after the first
visit a page costs only its HTML.

## Requirements

- PHP 8.3 or newer (8.4 recommended) with `mbstring`, `bcmath`, `intl`, `pdo_mysql`
- MySQL 8
- Composer

Node.js is **not** needed.

## Install

```bash
composer install
cp .env.example .env          # then set DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan key:generate
php artisan migrate
php artisan app:create-admin  # asks for name, email and password
php artisan serve
```

There is no default user and no sample data. The first administrator is created
with `php artisan app:create-admin`.

For production set `APP_ENV=production`, `APP_DEBUG=false` and run
`php artisan optimize`.

## Web server

Apache: `public/.htaccess` already turns on compression and one-year caching
(`mod_deflate` and `mod_headers` must be enabled).

nginx: add inside the `server` block:

```nginx
gzip on;
gzip_types text/css application/javascript application/json image/svg+xml;

location ~* \.(css|js|svg|png|jpg|jpeg|webp|ico|woff2)$ {
    add_header Cache-Control "public, max-age=31536000, immutable";
    try_files $uri =404;
}
```

## Front-end assets

Sources live in `resources/assets`. The files the browser gets are generated and
committed, so the server never builds anything:

```bash
php tools/build-assets.php          # rebuild public/css, public/js, public/icons.svg
php tools/build-assets.php --check  # fail if they are out of date (used by CI)
```

Run the build after changing a view, a menu icon, `erp.css` or `app.js`.

| Source | Purpose |
|---|---|
| `resources/assets/css/bootstrap*.min.css` | Bootstrap 5.3.3, unchanged |
| `resources/assets/css/fida.css` | fida-admin stylesheet, unchanged |
| `resources/assets/css/erp.css` | our additions (system fonts, icons, toasts, confirm dialog) |
| `resources/assets/js/app.js` | all JavaScript |
| `resources/assets/icons/fontawesome.json` | Font Awesome Free icon shapes |
| `resources/assets/safelist.txt` | classes and icons the build cannot detect |

The build keeps only CSS rules whose class names appear in the views or scripts.
**Write class names in full.** `'badge-'.$colour` will be removed by the build;
write `badge-success` or add the names to `safelist.txt`.

### Building pages

```blade
@extends('layouts.app', ['title' => __('app.dashboard.title')])

@section('content')
    <x-page-header :title="__('app.dashboard.title')" icon="gauge-high" />
    <x-icon name="box" />                 {{-- Font Awesome solid --}}
    <x-icon name="eye" set="regular" />   {{-- Font Awesome regular --}}
@endsection
```

- Sidebar entries: `config/menu.php`
- Every label: `lang/en/app.php` (Dari and Pashto can be added as `lang/fa`, `lang/ps`;
  the layout switches to right-to-left by itself)
- Success and error messages: `return back()->with('success', '...')` shows a toast
- Ask before a dangerous action: `<form data-confirm="Delete this?" ...>`
- Forms can be sent only once, which prevents double posting on slow connections

## Tests

```bash
php artisan test
```

GitHub Actions runs the migrations on MySQL 8 and the tests on every push. The tests
also fail when a page or the shared assets become too heavy, or when a page loads
anything from another server.

## Credits

- [Bootstrap](https://getbootstrap.com) 5.3.3, MIT licence
- [Font Awesome Free](https://fontawesome.com) 6.5.2 icons, CC BY 4.0 licence
- fida-admin template by Fida Technologies
