<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $title }}</title>
<style>
*{box-sizing:border-box}body{margin:0;padding:16px;font:14px/1.45 system-ui,-apple-system,'Segoe UI',Tahoma,Arial,sans-serif;color:#111;background:#f3f4f6}
.sheet{max-width:800px;margin:0 auto;background:#fff;padding:24px;border:1px solid #d1d5db}
.head{display:flex;justify-content:space-between;gap:16px;border-bottom:2px solid #111;padding-bottom:12px;margin-bottom:12px}
.head h1{margin:0;font-size:22px}.head .fa{font-size:18px;font-weight:700}.muted{color:#555;font-size:12px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:6px 24px;margin-bottom:12px}.grid div{display:flex;justify-content:space-between;border-bottom:1px dotted #9ca3af;padding:3px 0}
table{width:100%;border-collapse:collapse}th,td{border:1px solid #111;padding:6px 8px;text-align:left}th{background:#f3f4f6;font-size:12px}
.r{text-align:right}.c{text-align:center}.totals{width:50%;margin-left:auto;margin-top:12px}.totals td:first-child{background:#f3f4f6}
.sign{display:flex;justify-content:space-between;margin-top:48px}.sign div{border-top:1px solid #111;padding-top:4px;min-width:180px;text-align:center}
.note{margin-top:16px;text-align:center;font-size:12px}.bar{max-width:800px;margin:0 auto 12px;display:flex;gap:8px}
.bar button,.bar a{font:inherit;padding:8px 14px;border:1px solid #111;background:#fff;color:#111;text-decoration:none;cursor:pointer}
.void{color:#b91c1c;font-weight:700;border:2px solid #b91c1c;padding:4px 10px;display:inline-block;margin-bottom:8px}
[dir=rtl]{unicode-bidi:isolate}
@media print{body{background:#fff;padding:0}.sheet{border:0;padding:0}.bar{display:none}}
</style>
</head>
<body>
@php
    $code = $sale->currency->code;
    $unit = fn ($line) => $line->item->unit->symbol ?: $line->item->unit->name;
    $names = ['invoice' => ['Sales Bill', 'بل فروش'], 'quotation' => ['Quotation', 'نرخ نامه'], 'return' => ['Sale Return', 'برگشت فروش']][$sale->type];
    if ($delivery) {
        $names = ['Delivery Note', 'رسید تحویلی'];
    }
    // what the customer owes after this bill = this bill - received + what was owed before
    $remaining = \App\Support\Num::sub($sale->total, $sale->received);
    $grand = \App\Support\Num::add($remaining, $sale->previous_balance);
@endphp
<div class="bar">
    <button type="button" onclick="window.print()">{{ __('Print') }}</button>
    <a href="{{ route('sales.show', ['id' => $sale->id]) }}">{{ __('Back') }}</a>
</div>
<div class="sheet">
    <div class="head">
        <div>
            <h1>{{ setting('company_name', config('app.name')) }}</h1>
            <div class="muted">{{ setting('company_phone') }}{{ setting('company_email') ? ' · '.setting('company_email') : '' }}</div>
            <div class="muted">{{ setting('company_address') }}</div>
        </div>
        <div class="r">
            <div class="fa" dir="rtl">{{ setting('company_name_local') }}</div>
            <div><strong>{{ $names[0] }}</strong> · <span dir="rtl">{{ $names[1] }}</span></div>
        </div>
    </div>

    @if ($sale->status === 'cancelled')
        <div class="void">{{ __('CANCELLED') }}</div>
    @endif

    <div class="grid">
        <div><span>No. · <span dir="rtl">بل نمبر</span></span><strong>{{ $sale->number }}</strong></div>
        <div><span>Date · <span dir="rtl">تاریخ</span></span><strong>{{ fmt_date($sale->date) }}</strong></div>
        <div><span>Customer · <span dir="rtl">اسم مشتری</span></span><strong>{{ $sale->customer->name }}</strong></div>
        <div><span>Phone · <span dir="rtl">شماره تماس</span></span><strong>{{ $sale->customer->phone }}</strong></div>
        <div><span>Company · <span dir="rtl">اسم شرکت</span></span><strong>{{ $sale->customer->company }}</strong></div>
        <div><span>Address · <span dir="rtl">ادرس گیرنده</span></span><strong>{{ $sale->delivery_address ?: $sale->customer->address }}</strong></div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="c">#<br><span dir="rtl">شماره</span></th>
                <th>Details<br><span dir="rtl">تفصیلات</span></th>
                <th class="c">Unit<br><span dir="rtl">واحد</span></th>
                <th class="r">Qty<br><span dir="rtl">تعداد</span></th>
                @unless ($delivery)
                    <th class="r">Price<br><span dir="rtl">قیمت</span></th>
                    <th class="r">Total<br><span dir="rtl">جمله</span></th>
                @endunless
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->lines as $line)
                <tr>
                    <td class="c">{{ $loop->iteration }}</td>
                    <td>{{ $line->item->name }}{{ $line->item->code ? ' '.$line->item->code : '' }}{{ $line->item->packing ? ' · '.$line->item->packing : '' }}</td>
                    <td class="c">{{ $unit($line) }}</td>
                    <td class="r">{{ qty($line->qty) }}</td>
                    @unless ($delivery)
                        <td class="r">{{ \App\Support\Num::isZero($line->price) ? '-' : \App\Support\Num::format($line->price, 6, true) }}</td>
                        <td class="r">{{ \App\Support\Num::isZero($line->total) ? '-' : money($line->total) }}</td>
                    @endunless
                </tr>
            @endforeach
        </tbody>
    </table>

    @unless ($delivery)
        <table class="totals">
            @unless (\App\Support\Num::isZero($sale->discount))
                <tr><td>Discount · <span dir="rtl">تخفیف</span></td><td class="r">{{ money($sale->discount, $code) }}</td></tr>
            @endunless
            <tr><td>Total · <span dir="rtl">مجموع شد</span></td><td class="r"><strong>{{ money($sale->total, $code) }}</strong></td></tr>
            @if ($sale->type === 'invoice')
                <tr><td>Received · <span dir="rtl">رسید</span></td><td class="r">{{ \App\Support\Num::isZero($sale->received) ? '-' : money($sale->received, $code) }}</td></tr>
                <tr><td>Previous balance · <span dir="rtl">باقی مانده</span></td><td class="r">{{ \App\Support\Num::isZero($sale->previous_balance) ? '-' : money($sale->previous_balance, $code) }}</td></tr>
                <tr><td>Grand total · <span dir="rtl">مجموع کل</span></td><td class="r"><strong>{{ money($grand, $code) }}</strong></td></tr>
            @endif
        </table>
    @endunless

    <div class="grid" style="margin-top:16px">
        <div><span>Transport no. · <span dir="rtl">شماره ترانسپورت</span></span><strong>{{ $sale->transport_no }}</strong></div>
        <div><span>Driver · <span dir="rtl">راننده</span></span><strong>{{ $sale->driver }}</strong></div>
    </div>

    <div class="sign">
        <div>Signature / Stamp · <span dir="rtl">امضا / مهر</span></div>
        <div>Received by · <span dir="rtl">تسلیم شد</span></div>
    </div>

    @if (setting('invoice_note'))
        <div class="note" dir="auto">{{ setting('invoice_note') }}</div>
    @endif
</div>
</body>
</html>
