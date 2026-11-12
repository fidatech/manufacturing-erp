{{--
    Top of a report: company heading for the printed paper, the dates, and the print button.
    $range = true for from/to, false for a single "on" date.
--}}
@php
    $reportPeriod = ($range ?? true) ? fmt_date($from).' – '.fmt_date($to) : fmt_date($to);
    $reportTitle = $heading ?? ($title ?? __('Report'));
@endphp
@include('statement.letterhead', ['heading' => $reportTitle, 'period' => $reportPeriod, 'note' => __('Amounts in :base', ['base' => $base])])
<form method="GET" class="row g-2 align-items-end mb-3 no-print">
    @if ($range ?? true)
        @include('partials.date-filter')
    @else
        <div class="col-8 col-md-auto">
            <label class="form-label small mb-1">{{ __('On') }}</label>
            <x-date name="to" :value="$to" />
        </div>
    @endif
    <div class="col-auto"><button class="btn btn-primary" type="submit">{{ __('Show') }}</button></div>
    <div class="col-auto ms-auto"><button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button></div>
</form>
<div class="report-strip no-print">
    <span class="badge-soft primary"><x-icon name="calendar-days" class="me-1" />{{ $reportPeriod }}</span>
    <span class="badge-soft secondary">{{ __('Amounts in :base', ['base' => $base]) }}</span>
</div>
