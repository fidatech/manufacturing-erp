{{--
    Period bar of a statement: quick choices (this month, last month ...) and the two date boxes.
    $periods comes from App\Support\Periods::quick(). Extra filters go in $slot of the including view
    through @include(..., ['extra' => 'view.name']).
--}}
<div class="card stmt-period no-print">
    <div class="card-body">
        <div class="stmt-chips">
            @foreach ($periods as $period)
                <a class="stmt-chip {{ $period['current'] ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['from' => $period['from'], 'to' => $period['to']]) }}">{{ $period['label'] }}</a>
            @endforeach
        </div>
        <form method="GET" class="row g-2 align-items-end">
            @include('partials.date-filter')
            @isset($extra)
                @include($extra)
            @endisset
            <div class="col-auto"><button class="btn btn-primary" type="submit">{{ __('Show') }}</button></div>
        </form>
    </div>
</div>
