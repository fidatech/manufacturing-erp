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

## What it does

| Area | Screens |
|---|---|
| Sales | bills, quotations, delivery tracking, returns, printed bill and delivery note |
| Purchases | bills with transport and customs costs, purchase orders, goods on the way and received (GRN), supplier advances, returns |
| Stock | stock on hand with value, stock card, batches and expiry, opening stock, transfers, physical count |
| Money | money in, money out, expenses, transfer and exchange, day book (roznamcha), journal entries |
| Accounts | customers, suppliers, banks, sarafi, cash boxes, shareholders, employees, warehouse managers, loans, chart of accounts, statements |
| Owners | capital put in, money taken out, profit sharing |
| Assets | fixed and current, tangible and intangible, monthly depreciation and amortization |
| Employees | employee records, advances, monthly payroll with tax |
| Reports | profit and loss, balance sheet, trial balance, cash flow, who owes whom, sales, purchases |
| Office | documents, users, roles and permissions, audit log, company, currencies, rates, warehouses, units |

Laboratory & Quality Control and Manufacturing & Production are planned next.

### How it is built: three engines

1. **One accounts list.** Customers, suppliers, banks, cash boxes, shareholders, employees,
   expenses and everything else are accounts of a kind (`accounts`, `account_types`).
2. **One journal.** Every sale, purchase, payment and salary posts to `journal_entries` /
   `journal_lines` through `App\Services\Ledger`. Statements and all accounting reports read from it.
3. **One stock ledger.** Every movement of goods is a row in `stock_movements`, written by
   `App\Services\Stock`. Stock on hand, stock cards and batches read from it.

Rules the code enforces:

- Money and quantities use exact decimals (`App\Support\Num`, bcmath), never floats.
- Every paper is saved in one database transaction; a form can be sent only once.
- Stock can never go below zero. Goods leave oldest expiry first. Cost is the weighted average.
- Nothing is edited after saving: a paper is cancelled and entered again. Cancelled papers stay visible.
- Each account has one currency. Reports are in the main currency (USD) using the exchange
  rate of the day of each paper.
- Users choose the Gregorian or the Solar Hijri calendar; the database always stores Gregorian
  (`App\Support\Shamsi`, `ConvertShamsiDates` middleware, `<x-date>`).
- All screen text goes through `__()`, so Dari and Pashto can be added as `lang/fa.json` and `lang/ps.json`.

### Known limits (kept simple on purpose)

- Cancelling a purchase does not recalculate the average cost of its items.
- Balances in other currencies are not revalued when the exchange rate changes.
- Purchase orders are received in full; partial receipts are not tracked.
- Payroll months are Gregorian months. Attendance and leave are not included.
- Depreciation is straight line only.

A short guide for the people who use the system is in [docs/user-guide.md](docs/user-guide.md).

## Requirements

- PHP 8.3 or newer (8.4 recommended) with `mbstring`, `bcmath`, `intl`, `pdo_mysql`, `fileinfo`
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
with `php artisan app:create-admin`. The system starts with three currencies
(USD as the main one, AFN, PKR), a cash box for each, common units and the kinds
of account it needs; everything else is entered by the company.

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
- Labels: written in English inside `__('...')`; translations go in `lang/fa.json`, `lang/ps.json`
  (the layout switches to right-to-left by itself)
- A simple list-and-form screen: extend `App\Http\Controllers\CrudController` and declare
  its fields and columns (see `Setup/UnitController.php`)
- Dates: `<x-date name="date" :value="$iso" />` in forms and `fmt_date($date)` on screens
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
