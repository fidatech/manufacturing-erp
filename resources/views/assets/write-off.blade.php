@extends('layouts.app', ['title' => $title])

@section('content')
<x-page-header :title="$title" icon="chart-line" :crumbs="[[__('Assets'), route('assets.index')], [$title]]"
    :subtitle="$kind === 'tangible' ? __('Machines, furniture and other things you can touch lose a little value every month.') : __('Licences, software and other rights lose a little value every month.')" />

@can('assets.manage')
    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('assets.depreciate') }}" class="row g-2 align-items-end">
                @csrf
                <input type="hidden" name="kind" value="{{ $kind }}">
                <div class="col-8 col-md-auto">
                    <label class="form-label small mb-1" for="f-period">{{ __('Month') }}</label>
                    <input type="month" class="form-control" id="f-period" name="period" value="{{ now()->format('Y-m') }}" required>
                </div>
                <div class="col-4 col-md-auto"><button type="submit" class="btn btn-primary w-100">{{ __('Write off this month') }}</button></div>
                <div class="col-12 form-text">{{ __('Press once a month. Pressing twice does no harm.') }}</div>
            </form>
        </div>
    </div>
@endcan

<div class="card mb-4">
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Asset') }}</th><th class="text-end">{{ __('Cost') }}</th><th class="text-end">{{ __('Life') }}</th><th class="text-end">{{ __('Each month') }}</th><th class="text-end">{{ __('Written off') }}</th><th class="text-end">{{ __('Worth now (:base)', ['base' => $base]) }}</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['asset']->name }}</td>
                        <td class="text-end">{{ money($row['asset']->cost_base) }}</td>
                        <td class="text-end">{{ __(':months months', ['months' => $row['asset']->life_months]) }}</td>
                        <td class="text-end">{{ money($row['monthly']) }}</td>
                        <td class="text-end">{{ money($row['off']) }}</td>
                        <td class="text-end fw-semibold">{{ money($row['value']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No asset of this kind has a life in months yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($periods->isNotEmpty())
    <div class="card">
        <div class="card-header"><h5 class="card-title">{{ __('Months already written off') }}</h5></div>
        <div class="card-body">
            <table class="table w-100 keep-table">
                <thead><tr><th>{{ __('Month') }}</th><th>{{ __('Assets') }}</th><th class="text-end">{{ __('Written off (:base)', ['base' => $base]) }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($periods as $period)
                        <tr>
                            <td>{{ $period->period }}</td><td>{{ $period->assets }}</td><td class="text-end">{{ money($period->total) }}</td>
                            <td class="text-end">
                                @can('assets.manage')
                                    <form method="POST" action="{{ route('assets.undo') }}" class="d-inline" data-confirm="{{ __('Take back the write-off of :period?', ['period' => $period->period]) }}" data-confirm-ok="{{ __('Take it back') }}">
                                        @csrf
                                        <input type="hidden" name="period" value="{{ $period->period }}">
                                        <input type="hidden" name="kind" value="{{ $kind }}">
                                        <button type="submit" class="btn btn-sm btn-light-danger">{{ __('Take back') }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
