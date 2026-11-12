@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => __('Batch').' '.$batch->batch_no])

@section('content')
<x-page-header :title="__('Batch').' '.$batch->batch_no" icon="industry" :crumbs="[[__('Production Batches'), route('production.index')], [$batch->number]]">
    <a class="btn btn-outline-soft" href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank" rel="noopener"><x-icon name="print" class="me-1" /> {{ __('Print') }}</a>
    @if ($batch->status === 'posted')
        @can('lab.manage')
            <a class="btn btn-primary" href="{{ route('lab.create', ['batch' => $batch->id]) }}"><x-icon name="vial" class="me-1" /> {{ __('Lab test') }}</a>
        @endcan
        @can('production.manage')
            <form method="POST" action="{{ route('production.cancel', ['id' => $batch->id]) }}" class="d-inline" data-confirm="{{ __('Cancel :number?', ['number' => $batch->number]) }}" data-confirm-text="{{ __('The raw materials go back to stock and the product is taken out.') }}" data-confirm-ok="{{ __('Cancel it') }}">
                @csrf
                <button type="submit" class="btn btn-light-danger">{{ __('Cancel') }}</button>
            </form>
        @endcan
    @endif
</x-page-header>

<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Product') }}</div><strong>{{ $batch->item->label() }}</strong></div>
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Made') }}</div><strong>{{ qty($batch->qty) }} {{ $batch->item->unit->symbol ?: $batch->item->unit->name }}</strong> · {{ fmt_date($batch->date) }}</div>
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Expires on') }}</div><strong>{{ fmt_date($batch->expiry_date) ?: '-' }}</strong></div>
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Laboratory') }}</div>@include('production.qc')</div>
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Raw materials from') }}</div><strong>{{ $batch->warehouse->name }}</strong></div>
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Product put in') }}</div><strong>{{ $batch->toWarehouse->name }}</strong></div>
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Cost of the batch') }}</div><strong>{{ money($batch->cost_base, $base) }}</strong></div>
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Cost of one') }}</div><strong>{{ \App\Support\Num::format($batch->unit_cost, 4, true) }} {{ $base }}</strong></div>
        </div>
        <table class="table w-100 keep-table">
            <thead><tr><th>#</th><th>{{ __('Raw material or packing') }}</th><th class="text-end">{{ __('Used') }}</th><th class="text-end">{{ __('Cost (:base)', ['base' => $base]) }}</th></tr></thead>
            <tbody>
                @foreach ($batch->lines as $line)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $line->item->label() }}</td><td class="text-end text-nowrap">{{ qty($line->qty) }} {{ $line->item->unit->symbol }}</td><td class="text-end">{{ money($line->cost_base) }}</td></tr>
                @endforeach
                @php
                    $materials = \App\Support\Num::money(\App\Support\Num::sum($batch->lines->pluck('cost_base')));
                @endphp
                <tr><td colspan="3">{{ __('Other production cost') }}</td><td class="text-end">{{ money(\App\Support\Num::sub($batch->cost_base, $materials)) }}</td></tr>
                <tr class="fw-bold"><td colspan="3">{{ __('Total') }}</td><td class="text-end">{{ money($batch->cost_base, $base) }}</td></tr>
            </tbody>
        </table>
        @if ($batch->notes)
            <p class="mt-3 mb-0"><span class="text-muted">{{ __('Notes') }}:</span> {{ $batch->notes }}</p>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title">{{ __('Lab tests of this batch') }}</h5></div>
    <div class="card-body">
        <table class="table w-100 keep-table mb-0">
            <tbody>
                @forelse ($batch->tests->sortByDesc('id') as $test)
                    <tr><td><a href="{{ route('lab.show', ['id' => $test->id]) }}">{{ $test->number }}</a></td><td>{{ fmt_date($test->date) }}</td><td>{{ $test->tested_by }}</td><td class="text-end"><span class="badge-soft {{ $test->status === 'passed' ? 'success' : 'danger' }}">{{ $test->status === 'passed' ? __('Passed') : __('Failed') }}</span></td></tr>
                @empty
                    <tr><td class="text-muted">{{ __('This batch was not tested yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
