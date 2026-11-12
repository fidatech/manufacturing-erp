@php
    use App\Support\Num;

    $rtl = text_dir() === 'rtl';
    $company = ($rtl ? setting('company_name_local') : null) ?: (setting('company_name') ?: config('app.name'));
    $other = $rtl ? setting('company_name') : setting('company_name_local');
    $code = $account->currency->code;
    $party = $money || $account->type->is_party;
    $show = fn ($balance) => $party ? money(Num::abs($balance)) : money($balance);
    $tone = fn ($balance) => 't-'.$account->balanceTone($balance);
    $words = $account->balanceWords($closing);
    $contact = array_filter([setting('company_phone'), setting('company_email')]);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ text_dir() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('Account statement') }} · {{ $account->name }}</title>
<style>
:root{--ink:#111827;--muted:#6b7280;--line:#e5e7eb;--soft:#f6f7f9;--accent:#1f3a8a;--due:#15803d;--owe:#b91c1c}
*{box-sizing:border-box}
body{margin:0;padding:18px;font:13px/1.5 system-ui,-apple-system,'Segoe UI',Tahoma,Arial,sans-serif;color:var(--ink);background:#eef0f3;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.bar{max-width:820px;margin:0 auto 12px;display:flex;gap:8px}
.bar button,.bar a{font:inherit;font-weight:600;padding:9px 16px;border:1px solid var(--accent);border-radius:8px;background:var(--accent);color:#fff;text-decoration:none;cursor:pointer}
.bar a{background:#fff;color:var(--accent)}
.sheet{max-width:820px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.08);overflow:hidden}
.pad{padding:26px 30px}
.head{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:24px 30px;border-bottom:3px solid var(--accent)}
.brand{display:flex;align-items:center;gap:16px;min-width:0}
.brand img{max-height:70px;max-width:230px;object-fit:contain}
.brand h1{margin:0;font-size:20px;line-height:1.25}
.brand .alt{display:inline-block;font-size:14px;font-weight:600;color:#374151}
.small{font-size:11.5px;color:var(--muted)}
.doc{text-align:end;flex-shrink:0}
.doc h2{margin:0;font-size:19px;letter-spacing:.6px;text-transform:uppercase;color:var(--accent)}
.doc div{font-size:12px;color:#374151}
.info{display:grid;grid-template-columns:1.4fr 1fr;gap:16px;margin-bottom:16px}
.box{border:1px solid var(--line);border-radius:10px;padding:14px 16px}
.box .label{font-size:10.5px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--muted);margin-bottom:4px}
.box .name{font-size:17px;font-weight:800}
.box.now{background:var(--soft);text-align:end}
.box.now .amount{font-size:23px;font-weight:800;font-variant-numeric:tabular-nums;direction:ltr;unicode-bidi:isolate}
.tag{display:inline-block;margin-top:2px;padding:1px 10px;border-radius:999px;font-size:11px;font-weight:700;border:1px solid currentColor}
.t-due{color:var(--due)}.t-owe{color:var(--owe)}.t-capital{color:var(--accent)}.t-zero{color:var(--muted)}
.sums{display:grid;grid-template-columns:repeat(4,1fr);border:1px solid var(--line);border-radius:10px;margin-bottom:18px;overflow:hidden}
.sums div{padding:10px 14px;border-inline-end:1px solid var(--line)}
.sums div:last-child{border-inline-end:0;background:var(--soft)}
.sums span{display:block;font-size:10.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--muted)}
.sums b{font-size:15px;font-variant-numeric:tabular-nums;direction:ltr;unicode-bidi:isolate;display:inline-block}
table{width:100%;border-collapse:collapse;font-variant-numeric:tabular-nums}
thead th{background:var(--accent);color:#fff;font-size:10.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;padding:8px 10px;text-align:start}
td{padding:7px 10px;border-bottom:1px solid var(--line);vertical-align:top}
tbody tr:nth-child(even) td{background:#fafbfc}
.r{text-align:end;white-space:nowrap}
th.r{text-align:end}
.nw{white-space:nowrap}
.ref{font-size:11px;color:var(--muted)}
.open td{font-style:italic;color:#4b5563;background:var(--soft)!important}
tfoot td{font-weight:800;border-top:2px solid var(--ink);border-bottom:0;background:var(--soft)}
thead{display:table-header-group}
tr{break-inside:avoid}
.empty{text-align:center;color:var(--muted);padding:28px 0}
.close{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:16px;padding:12px 16px;border-radius:10px;border:2px solid var(--accent)}
.close b{font-size:17px;font-variant-numeric:tabular-nums}
.sign{display:flex;justify-content:space-between;gap:24px;margin-top:60px;break-inside:avoid}
.sign div{flex:1;max-width:210px;border-top:1px solid var(--ink);padding-top:5px;text-align:center;font-size:11.5px}
.foot{margin-top:22px;padding-top:10px;border-top:1px solid var(--line);display:flex;justify-content:space-between;gap:12px;font-size:10.5px;color:var(--muted)}
@page{size:A4;margin:10mm}
@media print{body{background:#fff;padding:0}.bar{display:none}.sheet{max-width:none;border-radius:0;box-shadow:none}.pad{padding:16px 4px 0}.head{padding:0 4px 14px}}
@media (max-width:600px){.head{flex-direction:column;align-items:flex-start}.doc{text-align:start}.info{grid-template-columns:1fr}.sums{grid-template-columns:1fr 1fr}}
</style>
</head>
<body>
<div class="bar">
    <button type="button" onclick="window.print()">{{ __('Print') }}</button>
    <a href="{{ route('statement', ['id' => $account->id, 'from' => $from, 'to' => $to]) }}">{{ __('Back') }}</a>
</div>
<div class="sheet">
    <div class="head">
        <div class="brand">
            @if ($logo)
                <img src="{{ $logo }}" alt="">
            @endif
            <div>
                <h1>{{ $company }}</h1>
                @if ($other && $other !== $company)
                    <div class="alt" dir="auto">{{ $other }}</div>
                @endif
                @if ($contact)
                    <div class="small" dir="ltr">{{ implode(' · ', $contact) }}</div>
                @endif
                @if (setting('company_address'))
                    <div class="small">{{ setting('company_address') }}</div>
                @endif
            </div>
        </div>
        <div class="doc">
            <h2>{{ __('Account statement') }}</h2>
            <div>{{ fmt_date($from) }} – {{ fmt_date($to) }}</div>
            <div>{{ __('Amounts in :base', ['base' => $code]) }}</div>
        </div>
    </div>

    <div class="pad">
        <div class="info">
            <div class="box">
                <div class="label">{{ __($account->type->name) }}</div>
                <div class="name">{{ $account->name }}</div>
                @if ($account->company)
                    <div>{{ $account->company }}</div>
                @endif
                @if ($account->phone)
                    <div class="small">{{ __('Phone') }}: <span dir="ltr">{{ $account->phone }}</span></div>
                @endif
                @if ($account->address)
                    <div class="small">{{ __('Address') }}: {{ $account->address }}</div>
                @endif
            </div>
            <div class="box now">
                <div class="label">{{ __('Balance on :date', ['date' => fmt_date($to)]) }}</div>
                <div class="amount {{ $tone($closing) }}">{{ $show($closing) }} {{ $code }}</div>
                @if ($words !== '')
                    <span class="tag {{ $tone($closing) }}">{{ $words }}</span>
                @endif
            </div>
        </div>

        <div class="sums">
            <div><span>{{ __('Balance before') }}</span><b>{{ $show($opening) }}</b></div>
            <div><span>{{ $columns[0] }}</span><b>{{ money($debit) }}</b></div>
            <div><span>{{ $columns[1] }}</span><b>{{ money($credit) }}</b></div>
            <div><span>{{ __('Balance now') }}</span><b class="{{ $tone($closing) }}">{{ $show($closing) }}</b></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Details') }}</th>
                    <th class="r">{{ $columns[0] }}</th>
                    <th class="r">{{ $columns[1] }}</th>
                    <th class="r">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr class="open">
                    <td></td>
                    <td class="nw">{{ fmt_date($from) }}</td>
                    <td colspan="3">{{ __('Balance before :date', ['date' => fmt_date($from)]) }}</td>
                    <td class="r">{{ $show($opening) }}</td>
                </tr>
                @foreach ($rows as $row)
                    <tr>
                        <td class="ref">{{ $loop->iteration }}</td>
                        <td class="nw">{{ fmt_date($row['line']->date) }}</td>
                        <td>
                            {{ $row['line']->memo ?: ($row['line']->entry->description ?: $row['kind']['label']) }}
                            <div class="ref">{{ $row['kind']['label'] }} · {{ $row['line']->entry->number }}</div>
                        </td>
                        <td class="r">{{ Num::isZero($row['line']->debit) ? '' : money($row['line']->debit) }}</td>
                        <td class="r">{{ Num::isZero($row['line']->credit) ? '' : money($row['line']->credit) }}</td>
                        <td class="r {{ $tone($row['balance']) }}"><b>{{ $show($row['balance']) }}</b></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3">{{ __('Total') }} · {{ count($rows) }}</td>
                    <td class="r">{{ money($debit) }}</td>
                    <td class="r">{{ money($credit) }}</td>
                    <td class="r {{ $tone($closing) }}">{{ $show($closing) }}</td>
                </tr>
            </tfoot>
        </table>
        @unless ($rows)
            <div class="empty">{{ __('Nothing happened in these dates') }}</div>
        @endunless

        <div class="close">
            <span>{{ __('Balance on :date', ['date' => fmt_date($to)]) }}</span>
            <b class="{{ $tone($closing) }}">{{ $account->describeBalance($closing) }}</b>
        </div>

        <div class="sign">
            <div>{{ __('Prepared by') }}</div>
            <div>{{ __('Checked by') }}</div>
            <div>{{ $account->type->is_party ? __('Confirmed by :name', ['name' => $account->name]) : __('Received by') }}</div>
        </div>

        <div class="foot">
            <span>{{ __('Printed on :date by :user', ['date' => fmt_date(today_iso()), 'user' => auth()->user()->name]) }}</span>
            <span>{{ $company }}</span>
        </div>
    </div>
</div>
</body>
</html>
