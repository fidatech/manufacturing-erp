{{-- Signature lines and the print stamp at the foot of a printed statement. --}}
<div class="stmt-foot">
    <div class="stmt-sign">
        <div>{{ __('Prepared by') }}</div>
        <div>{{ __('Checked by') }}</div>
        <div>{{ $third ?? __('Received by') }}</div>
    </div>
    <div class="stmt-stamp">{{ __('Printed on :date by :user', ['date' => fmt_date(today_iso()), 'user' => auth()->user()->name]) }}</div>
</div>
