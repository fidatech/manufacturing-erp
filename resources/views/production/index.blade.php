@extends('layouts.app', ['title' => __('Production Batches')])

@section('content')
<x-page-header :title="__('Production Batches')" icon="industry" :subtitle="__('The daily production record: every batch that was made.')">
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
    @can('production.manage')
        <a class="btn btn-primary" href="{{ route('production.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Record production') }}</a>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3 no-print">
            @include('partials.date-filter', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null])
            <div class="col">
                <label class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Product or batch number') }}">
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1">{{ __('Laboratory') }}</label>
                <select class="form-select" name="qc">
                    <option value="">{{ __('All') }}</option>
                    @foreach (['pending' => __('Not tested yet'), 'passed' => __('Passed'), 'failed' => __('Failed')] as $key => $name)
                        <option value="{{ $key }}" @selected(($filters['qc'] ?? '') === $key)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Batch') }}</th><th>{{ __('Product') }}</th><th class="text-end">{{ __('Made') }}</th><th class="text-end">{{ __('Cost of one (:base)', ['base' => $base]) }}</th><th>{{ __('Laboratory') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($batches as $batch)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($batch->date) }}</td>
                        <td class="text-nowrap"><a class="fw-semibold" href="{{ route('production.show', ['id' => $batch->id]) }}">{{ $batch->batch_no }}</a></td>
                        <td>{{ $batch->item->label() }}</td>
                        <td class="text-end text-nowrap">{{ qty($batch->qty) }} {{ $batch->item->unit->symbol ?: $batch->item->unit->name }}</td>
                        <td class="text-end">{{ \App\Support\Num::format($batch->unit_cost, 2) }}</td>
                        <td>@include('production.qc')</td>
                        <td class="text-end no-print"><a class="btn btn-sm btn-light-secondary" href="{{ route('production.show', ['id' => $batch->id]) }}">{{ __('Open') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $batches->links('partials.pagination') }}
    </div>
</div>
@endsection
