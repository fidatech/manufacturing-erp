@php
    use App\Support\Num;

    $rtl = text_dir() === 'rtl';
    // right-to-left languages show the company name written in Dari / Pashto
    $company = ($rtl ? setting('company_name_local') : null) ?: (setting('company_name') ?: config('app.name'));
    $sellCode = $exchange->sellAccount->currency->code;
    $buyCode = $exchange->buyAccount->currency->code;
    $sign = $exchange->operation === 'divide' ? '÷' : '×';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ text_dir() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('Exchange receipt') }} {{ $exchange->number }}</title>
<style>
*{box-sizing:border-box}body{margin:0;padding:16px;font:14px/1.5 system-ui,-apple-system,'Segoe UI',Tahoma,Arial,sans-serif;color:#111;background:#f3f4f6}
.bar{max-width:640px;margin:0 auto 12px;display:flex;gap:8px}
.bar button,.bar a{font:inherit;padding:8px 14px;border:1px solid #111;border-radius:6px;background:#fff;color:#111;text-decoration:none;cursor:pointer}
.sheet{max-width:640px;margin:0 auto;background:#fff;padding:28px;border:1px solid #d1d5db;border-radius:10px}
.head{display:flex;align-items:center;gap:16px;border-bottom:2px solid #111;padding-bottom:14px}
.head img{max-height:72px;max-width:160px}
.head h1{margin:0;font-size:22px;line-height:1.25}
.muted{color:#555;font-size:12.5px}
.title{display:flex;justify-content:space-between;align-items:baseline;gap:12px;margin:16px 0 12px}
.title h2{margin:0;font-size:17px;text-transform:uppercase;letter-spacing:.5px}
.void{color:#b91c1c;font-weight:700;border:2px solid #b91c1c;border-radius:6px;padding:2px 10px;display:inline-block;margin-bottom:10px}
.rows{border:1px solid #d1d5db;border-radius:8px;overflow:hidden}
.rows div{display:flex;justify-content:space-between;gap:16px;padding:9px 14px;border-bottom:1px solid #e5e7eb}
.rows div:last-child{border-bottom:0}
.rows span{color:#555}
.rows strong{text-align:end}
.deal{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:12px;margin:16px 0;text-align:center}
.deal .box{border:1px solid #111;border-radius:8px;padding:12px 8px}
.deal .box small{display:block;color:#555;font-size:12px}
.deal .box b{display:block;font-size:21px;font-variant-numeric:tabular-nums;direction:ltr}
.deal .rate{font-size:13px;color:#333;direction:ltr;white-space:nowrap}
.deal .rate b{display:block;font-size:16px}
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
    <a href="{{ route('exchange.index') }}">{{ __('Back') }}</a>
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
            @if (setting('company_address'))
                <div class="muted">{{ __('Address') }}: {{ setting('company_address') }}</div>
            @endif
        </div>
    </div>

    <div class="title">
        <h2>{{ __('Exchange receipt') }}</h2>
        <strong dir="ltr">{{ $exchange->number }}</strong>
    </div>

    @if ($exchange->isCancelled())
        <div class="void">{{ __('DELETED') }}</div>
    @endif

    <div class="rows">
        <div><span>{{ __('Date') }}</span><strong>{{ fmt_date($exchange->date) }}</strong></div>
        <div><span>{{ __('Account') }}</span><strong>{{ $exchange->sellAccount->name }} → {{ $exchange->buyAccount->name }}</strong></div>
        @if ($exchange->description)
            <div><span>{{ __('Description') }}</span><strong>{{ $exchange->description }}</strong></div>
        @endif
    </div>

    <div class="deal">
        <div class="box"><small>{{ __('Sold') }}</small><b>{{ money($exchange->sell_amount) }} {{ $sellCode }}</b></div>
        <div class="rate">{{ __('Rate') }}<b>{{ $sign }} {{ Num::format($exchange->rate, 6, true) }}</b></div>
        <div class="box"><small>{{ __('Bought') }}</small><b>{{ money($exchange->buy_amount) }} {{ $buyCode }}</b></div>
    </div>

    <div class="rows">
        <div><span>{{ __('Seller currency') }}</span><strong>{{ $sellCode }}</strong></div>
        <div><span>{{ __('Selling amount') }}</span><strong dir="ltr">{{ money($exchange->sell_amount, $sellCode) }}</strong></div>
        <div><span>{{ __('Exchange rate') }}</span><strong dir="ltr">{{ Num::format($exchange->rate, 6, true) }}</strong></div>
        <div><span>{{ __('Operating type') }}</span><strong>{{ $sign }} {{ $exchange->operation === 'divide' ? __('Divide') : __('Multiply') }}</strong></div>
        <div><span>{{ __('Buyer currency') }}</span><strong>{{ $buyCode }}</strong></div>
        <div><span>{{ __('Buying amount') }}</span><strong dir="ltr">{{ money($exchange->buy_amount, $buyCode) }}</strong></div>
    </div>

    <div class="sign">
        <div>{{ __('Signature / Stamp') }}</div>
        <div>{{ __('Received by') }}</div>
    </div>
    <div class="foot">{{ __('Printed on :date by :user', ['date' => fmt_date(today_iso()), 'user' => auth()->user()->name]) }}{{ $exchange->creator ? ' · '.__('Made by :user', ['user' => $exchange->creator->name]) : '' }}</div>
</div>
</body>
</html>
