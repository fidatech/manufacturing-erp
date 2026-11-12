{{--
    Company heading of a printed statement or report. Hidden on the screen, shown on paper.
    $heading = name of the paper, $period = the dates it covers, $note = small extra line.
--}}
@php $logo = \App\Http\Controllers\Setup\CompanyController::logoUrl(); @endphp
<div class="letterhead">
    <div class="letterhead-company">
        @if ($logo)
            <img src="{{ $logo }}" alt="">
        @endif
        <div>
            <div class="letterhead-name">{{ setting('company_name') ?: config('app.name') }}</div>
            @if (setting('company_name_local'))
                <div class="letterhead-local" dir="auto">{{ setting('company_name_local') }}</div>
            @endif
            <div class="letterhead-contact">{{ implode(' · ', array_filter([setting('company_phone'), setting('company_email'), setting('company_address')])) }}</div>
        </div>
    </div>
    <div class="letterhead-doc">
        <div class="letterhead-title">{{ $heading }}</div>
        @if (! empty($period))
            <div>{{ $period }}</div>
        @endif
        @if (! empty($note))
            <div>{{ $note }}</div>
        @endif
    </div>
</div>
