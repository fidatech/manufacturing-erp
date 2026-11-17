@php
    use App\Support\Num;

    $rtl = text_dir() === 'rtl';
    $company = ($rtl ? setting('company_name_local') : null) ?: (setting('company_name') ?: config('app.name'));
    $amountOf = fn ($line) => Num::isPositive($line->debit) ? $line->debit : $line->credit;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ text_dir() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $title }} {{ $entry->number }}</title>
<style>
*{box-sizing:border-box}body{margin:0;padding:16px;font:14px/1.5 system-ui,-apple-system,'Segoe UI',Tahoma,Arial,sans-serif;color:#111;background:#f3f4f6}
.bar{max-width:680px;margin:0 auto 12px;display:flex;gap:8px}
.bar button,.bar a{font:inherit;padding:8px 14px;border:1px solid #111;border-radius:6px;background:#fff;color:#111;text-decoration:none;cursor:pointer}
.sheet{max-width:680px;margin:0 auto;background:#fff;padding:28px;border:1px solid #d1d5db;border-radius:10px}
.head{display:flex;align-items:center;gap:16px;border-bottom:2px solid #111;padding-bottom:14px}
.head img{max-height:72px;max-width:180px}
.head h1{margin:0;font-size:22px;line-height:1.25}
.muted{color:#555;font-size:12.5px}
.title{display:flex;justify-content:space-between;align-items:baseline;gap:12px;margin:16px 0 12px}
.title h2{margin:0;font-size:18px;text-transform:uppercase;letter-spacing:.5px}
.void{color:#b91c1c;font-weight:700;border:2px solid #b91c1c;border-radius:6px;padding:2px 10px;display:inline-block;margin-bottom:10px}
.rows{border:1px solid #d1d5db;border-radius:8px;overflow:hidden;margin-bottom:14px}
.rows div{display:flex;justify-content:space-between;gap:16px;padding:9px 14px;border-bottom:1px solid #e5e7eb}
.rows div:last-child{border-bottom:0}
.rows span{color:#555}
.rows strong{text-align:end}
.tt{display:flex;justify-content:space-between;align-items:center;gap:12px;border:2px solid #111;border-radius:8px;padding:10px 14px;margin-bottom:14px}
.tt b{font-size:20px;letter-spacing:1px;direction:ltr}
table{width:100%;border-collapse:collapse;margin-bottom:14px;font-variant-numeric:tabular-nums}
th{background:#111;color:#fff;font-size:11px;text-transform:uppercase;letter-spacing:.5px;padding:7px 10px;text-align:start}
td{padding:8px 10px;border-bottom:1px solid #e5e7eb}
.r{text-align:end;white-space:nowrap;direction:ltr}
.total{display:flex;justify-content:space-between;align-items:center;border:2px solid #111;border-radius:8px;padding:12px 16px}
.total b{font-size:22px;direction:ltr;font-variant-numeric:tabular-nums}
.sign{display:flex;justify-content:space-between;gap:24px;margin-top:56px}
.sign div{border-top:1px solid #111;padding-top:4px;min-width:170px;text-align:center;font-size:12.5px}
.foot{margin-top:18px;text-align:center;font-size:11px;color:#666}
@media print{body{background:#fff;padding:0}.sheet{border:0;border-radius:0;padding:0;max-width:none}.bar{display:none}}
</style>
{{ \App\Support\Fonts::style() }}
</head>
<body>
<div class="bar">
    <button type="button" onclick="window.print()">{{ __('Print') }}</button>
    <a href="{{ route('money.index') }}">{{ __('Back') }}</a>
</div>
<div class="sheet">
    <div class="head">
        @if ($logo)
            <img src="{{ $logo }}" alt="">
        @endif
        <div>
            <h1>{{ $company }}</h1>
            @if (setting('company_phone'))
                <div class="muted">{{ __('Phone') }}: <span dir="ltr">{{ setting('company_phone') }}</span></div>
            @endif
            @if (company_address())
                <div class="muted">{{ __('Address') }}: {{ company_address() }}</div>
            @endif
        </div>
    </div>

    <div class="title">
        <h2>{{ $title }}</h2>
        <strong dir="ltr">{{ $entry->number }}</strong>
    </div>

    @if ($entry->isVoid())
        <div class="void">{{ __('CANCELLED') }}</div>
    @endif

    @if ($entry->reference)
        <div class="tt"><span>{{ $isTt ? __('TT number') : __('Reference') }}</span><b>{{ $entry->reference }}</b></div>
    @endif

    <div class="rows">
        <div><span>{{ __('Date') }}</span><strong>{{ fmt_date($entry->date) }}</strong></div>
        @if ($person)
            <div><span>{{ $entry->type === 'receipt' ? __('Received from') : ($entry->type === 'transfer' ? __('Move the money to') : __('Paid to')) }}</span><strong>{{ $person->name }}@if ($person->code) <span class="muted" dir="ltr">· {{ $person->code }}</span>@endif</strong></div>
        @endif
        @if ($entry->description)
            <div><span>{{ __('Details') }}</span><strong>{{ $entry->description }}</strong></div>
        @endif
    </div>

    <table>
        <thead><tr><th>{{ $entry->type === 'receipt' ? __('Put the money in') : __('Paid from') }}</th><th class="r">{{ __('Amount') }}</th></tr></thead>
        <tbody>
            @foreach ($moneyLines as $line)
                <tr>
                    <td>{{ $line->account->name }}{{ $line->account->type->key === 'bank' && $line->account->metaValue('account_number') ? ' · '.$line->account->metaValue('account_number') : '' }}</td>
                    <td class="r">{{ money($amountOf($line), $line->account->currency->code) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($personLine)
        <div class="total">
            <span>{{ __('Total') }} ({{ $person->currency->code }})</span>
            <b>{{ money($amountOf($personLine), $person->currency->code) }}</b>
        </div>
    @endif

    <div class="sign">
        <div>{{ __('Signature / Stamp') }}</div>
        <div>{{ $entry->type === 'receipt' ? __('Paid by') : __('Received by') }}</div>
    </div>
    <div class="foot">{{ __('Printed on :date by :user', ['date' => fmt_date(today_iso()), 'user' => auth()->user()->name]) }}{{ $entry->creator ? ' · '.__('Made by :user', ['user' => $entry->creator->name]) : '' }}</div>
</div>
</body>
</html>
