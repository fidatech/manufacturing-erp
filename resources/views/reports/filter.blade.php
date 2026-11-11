{{-- Date filter and print button of a report. $range = true for from/to, false for a single "on" date. --}}
<form method="GET" class="row g-2 align-items-end mb-3 no-print">
    @if ($range ?? true)
        @include('partials.date-filter')
    @else
        <div class="col-8 col-md-auto">
            <label class="form-label small mb-1">{{ __('On') }}</label>
            <x-date name="to" :value="$to" />
        </div>
    @endif
    <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
    <div class="col-auto ms-auto"><button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button></div>
</form>
<p class="mb-3"><strong>{{ setting('company_name', config('app.name')) }}</strong> · {{ ($range ?? true) ? fmt_date($from).' – '.fmt_date($to) : fmt_date($to) }} · {{ __('Amounts in :base', ['base' => $base]) }}</p>
