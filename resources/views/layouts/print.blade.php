{{--
    A report as a clean paper with the company logo: no menu, no buttons, ready for the printer or "Save as PDF".
    A screen becomes printable by extending this layout instead of layouts.app when the address has ?print=1.
    The section "doc" holds the name of the paper and its dates (top right).
--}}
@php
    $rtl = text_dir() === 'rtl';
    $company = ($rtl ? setting('company_name_local') : null) ?: (setting('company_name') ?: config('app.name'));
    $other = $rtl ? setting('company_name') : setting('company_name_local');
    $logo = \App\Http\Controllers\Setup\CompanyController::logoDataUri();
    $contact = array_filter([setting('company_phone'), setting('company_email')]);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ text_dir() }}" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $title ?? __('Report') }} | {{ $company }}</title>
<link rel="stylesheet" href="{{ asset_v($rtl ? 'css/app.rtl.css' : 'css/app.css') }}">
<style>
:root{--paper-accent:#1f3a8a;--paper-line:#e5e7eb;--paper-soft:#f6f7f9}
body{margin:0;padding:18px;background:#eef0f3;color:#111827;font-size:13px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.paper-bar{max-width:860px;margin:0 auto 12px;display:flex;gap:8px}
.paper-bar button,.paper-bar a{font:inherit;font-weight:600;padding:9px 16px;border:1px solid var(--paper-accent);border-radius:8px;background:var(--paper-accent);color:#fff;text-decoration:none;cursor:pointer}
.paper-bar a{background:#fff;color:var(--paper-accent)}
.paper{max-width:860px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.08);overflow:hidden}
.paper-head{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:24px 30px;border-bottom:3px solid var(--paper-accent)}
.paper-brand{display:flex;align-items:center;gap:16px;min-width:0}
.paper-brand img{max-height:70px;max-width:230px;object-fit:contain}
.paper-brand h1{margin:0;font-size:20px;line-height:1.25;font-weight:800;color:#111827}
.paper-alt{display:inline-block;font-size:14px;font-weight:600;color:#374151}
.paper-small{font-size:11.5px;color:#6b7280}
.paper-doc{text-align:end;flex-shrink:0;font-size:12px;color:#374151}
.paper-doc h2{margin:0;font-size:19px;font-weight:800;letter-spacing:.6px;text-transform:uppercase;color:var(--paper-accent)}
.paper-body{padding:22px 30px 26px}
.paper-body .page-header,.paper-body .no-print,.paper-body .letterhead,.paper-body .report-strip{display:none!important}
.paper-body form,.paper-body .btn,.paper-body .attachments{display:none!important}
.paper-body .card{border:0;box-shadow:none;background:none;margin:0}
.paper-body .card+.card{margin-top:16px}.paper-body .card-header{padding:0 0 6px;background:none;border:0}.paper-body .card-title{font-size:14px;font-weight:800;color:var(--paper-accent)}
.paper-body .card-body{padding:0}
.paper-body a{color:inherit;text-decoration:none}
.paper-body h5{margin:18px 0 8px;font-size:14px;font-weight:800;color:var(--paper-accent)}
.paper-body .table{margin-bottom:14px;font-variant-numeric:tabular-nums;color:#111827}
.paper-body .table>:not(caption)>*>*{padding:7px 10px;border-bottom:1px solid var(--paper-line);background:none;color:inherit}
.paper-body .table thead th{background:var(--paper-accent);color:#fff;font-size:10.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;white-space:nowrap}
.paper-body .table .table-light>*{background:var(--paper-soft);font-weight:800;color:var(--paper-accent)}
.paper-body .table tr.fw-bold>*{background:var(--paper-soft);border-top:1px solid #111827}
.paper-body .table thead{display:table-header-group}
.paper-body .table tr{break-inside:avoid}
.paper-body .form-text{font-size:11px;color:#6b7280}
.paper-body .stmt{gap:14px}.paper-body .stmt-id{border:1px solid var(--paper-line);border-top:1px solid var(--paper-line);border-radius:10px}.paper-body .stmt-id>.card-body{padding:14px 16px}.paper-body .stmt-who .avatar{display:none}.paper-body .stmt-cards>*{width:25%}.paper-body .stat-card{border:1px solid var(--paper-line);border-radius:10px;padding:10px 14px;gap:2px}.paper-body .stat-card .sc-icon{display:none}.paper-body .stat-card .sc-value{font-size:16px}.paper-body .stmt-ledger-head{padding:6px 0;border:0}.paper-body .stmt-table thead th{position:static}.paper-body .stmt-close{margin:12px 0 0;border:2px solid var(--paper-accent);background:none}.paper-body .stmt-foot,.paper-body .stmt-legend{display:none}.paper-body .badge-soft{background:none;padding:0;color:#374151}
.paper-body .table td,.paper-body .table th{display:table-cell}.paper-body .table tr{display:table-row}
.paper-sign{display:flex;justify-content:space-between;gap:24px;margin-top:56px;break-inside:avoid}
.paper-sign div{flex:1;max-width:210px;border-top:1px solid #111827;padding-top:5px;text-align:center;font-size:11.5px}
.paper-foot{margin-top:22px;padding-top:10px;border-top:1px solid var(--paper-line);display:flex;justify-content:space-between;gap:12px;font-size:10.5px;color:#6b7280}
@page{size:A4;margin:10mm}
@media print{body{background:#fff;padding:0}.paper-bar{display:none}.paper{max-width:none;border-radius:0;box-shadow:none}.paper-head{padding:0 4px 14px}.paper-body{padding:16px 4px 0}}
@media (max-width:600px){.paper-head{flex-direction:column;align-items:flex-start}.paper-doc{text-align:start}}
</style>
</head>
<body>
<div class="paper-bar">
    <button type="button" onclick="window.print()">{{ __('Print') }}</button>
    <a href="{{ request()->fullUrlWithoutQuery(['print']) }}">{{ __('Back') }}</a>
</div>
<div class="paper">
    <div class="paper-head">
        <div class="paper-brand">
            @if ($logo)
                <img src="{{ $logo }}" alt="">
            @endif
            <div>
                <h1>{{ $company }}</h1>
                @if ($other && $other !== $company)
                    <div class="paper-alt" dir="auto">{{ $other }}</div>
                @endif
                @if ($contact)
                    <div class="paper-small" dir="ltr">{{ implode(' · ', $contact) }}</div>
                @endif
                @if (setting('company_address'))
                    <div class="paper-small">{{ setting('company_address') }}</div>
                @endif
            </div>
        </div>
        <div class="paper-doc">
            @hasSection('doc')
                @yield('doc')
            @else
                <h2>{{ $title ?? __('Report') }}</h2>
            @endif
        </div>
    </div>
    <div class="paper-body">
        @yield('content')

        <div class="paper-sign">
            <div>{{ __('Prepared by') }}</div>
            <div>{{ __('Checked by') }}</div>
            <div>{{ __('Approved by') }}</div>
        </div>
        <div class="paper-foot">
            <span>{{ __('Printed on :date by :user', ['date' => fmt_date(today_iso()), 'user' => auth()->user()->name]) }}</span>
            <span>{{ $company }}</span>
        </div>
    </div>
</div>
</body>
</html>
