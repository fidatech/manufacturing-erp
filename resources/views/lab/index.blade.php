@extends('layouts.app', ['title' => __('Lab Tests')])

@section('content')
<x-page-header :title="__('Lab Tests')" icon="vial" :subtitle="__('Quality checks of the batches we make and of the raw materials that arrive.')">
    @can('lab.manage')
        <a class="btn btn-primary" href="{{ route('lab.create', ['subject' => 'product']) }}"><x-icon name="plus" class="me-1" /> {{ __('Test a batch') }}</a>
        <a class="btn btn-outline-soft" href="{{ route('lab.create', ['subject' => 'raw']) }}"><x-icon name="plus" class="me-1" /> {{ __('Test a raw material') }}</a>
    @endcan
</x-page-header>

@if ($waiting->isNotEmpty())
    <div class="card mb-4">
        <div class="card-header"><h5 class="card-title">{{ __('Batches waiting for a test') }}</h5></div>
        <div class="card-body">
            <table class="table w-100 keep-table mb-0">
                <tbody>
                    @foreach ($waiting as $batch)
                        <tr>
                            <td><a href="{{ route('production.show', ['id' => $batch->id]) }}">{{ $batch->batch_no }}</a> · {{ $batch->item->label() }}</td>
                            <td class="text-nowrap">{{ fmt_date($batch->date) }}</td>
                            <td class="text-end">@can('lab.manage')<a class="btn btn-sm btn-primary" href="{{ route('lab.create', ['batch' => $batch->id]) }}">{{ __('Test') }}</a>@endcan</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3">
            @include('partials.date-filter', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null])
            <div class="col">
                <label class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}">
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1">{{ __('Result') }}</label>
                <select class="form-select" name="status">
                    <option value="">{{ __('All') }}</option>
                    <option value="passed" @selected(($filters['status'] ?? '') === 'passed')>{{ __('Passed') }}</option>
                    <option value="failed" @selected(($filters['status'] ?? '') === 'failed')>{{ __('Failed') }}</option>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Tested') }}</th><th>{{ __('Batch') }}</th><th>{{ __('Tested by') }}</th><th>{{ __('Result') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($tests as $test)
                    <tr>
                        <td class="text-nowrap"><a class="fw-semibold" href="{{ route('lab.show', ['id' => $test->id]) }}">{{ $test->number }}</a></td>
                        <td class="text-nowrap">{{ fmt_date($test->date) }}</td>
                        <td>{{ $test->item->label() }}<div class="small text-muted">{{ $test->subject === 'raw' ? __('Raw material') : __('Product') }}</div></td>
                        <td>{{ $test->batch_no }}</td>
                        <td>{{ $test->tested_by }}</td>
                        <td><span class="badge-soft {{ $test->status === 'passed' ? 'success' : 'danger' }}">{{ $test->status === 'passed' ? __('Passed') : __('Failed') }}</span></td>
                        <td class="text-end"><a class="btn btn-sm btn-light-secondary" href="{{ route('lab.show', ['id' => $test->id]) }}">{{ __('Open') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $tests->links('partials.pagination') }}
    </div>
</div>
@endsection
