{{--
    Top of a report: the dates and the print button on the screen;
    on the printed paper (?print=1) only the name of the report and its dates, beside the company logo.
    $range = true for from/to, false for a single "on" date.
--}}
@php
    $reportPeriod = ($range ?? true) ? fmt_date($from).' – '.fmt_date($to) : fmt_date($to);
    $reportTitle = $heading ?? ($title ?? __('Report'));
@endphp
@if (request()->boolean('print'))
    @section('doc')
        <h2>{{ $reportTitle }}</h2>
        <div>{{ $reportPeriod }}</div>
        <div>{{ __('Amounts in :base', ['base' => $base]) }}</div>
    @endsection
@else
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
        <div class="col-auto ms-auto"><a class="btn btn-outline-soft" href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank" rel="noopener"><x-icon name="print" class="me-1" /> {{ __('Print') }}</a></div>
    </form>
    <div class="report-strip no-print">
        <span class="badge-soft primary"><x-icon name="calendar-days" class="me-1" />{{ $reportPeriod }}</span>
        <span class="badge-soft secondary">{{ __('Amounts in :base', ['base' => $base]) }}</span>
    </div>
@endif
