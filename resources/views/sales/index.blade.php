@php
    $heading = ['delivery' => __('Delivery notes'), 'dispatch' => __('Dispatch planning')][$view] ?? $names[$type];
@endphp
@extends('layouts.app', ['title' => $heading])

@section('content')
<x-page-header :title="$heading" icon="file-invoice-dollar" :subtitle="$view === 'dispatch' ? __('Sales that still have to be sent or delivered, oldest first.') : null">
    @can('sales.manage')
        <a class="btn btn-primary" href="{{ route('sales.create', ['type' => $type]) }}"><x-icon name="plus" class="me-1" /> {{ __('Add :name', ['name' => \App\Http\Controllers\Trade\SaleController::singular($type)]) }}</a>
    @endcan
</x-page-header>

@unless ($view)
    @include('partials.type-tabs', ['routeName' => 'sales.index'])
@endunless

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="type" value="{{ $type }}">
            @if ($view)
                <input type="hidden" name="view" value="{{ $view }}">
            @endif
            @include('partials.date-filter', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null])
            <div class="col">
                <label class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Number or customer') }}">
            </div>
            @if ($type === 'invoice')
                <div class="col-6 col-md-auto">
                    <label class="form-label small mb-1">{{ __('Delivery') }}</label>
                    <select class="form-select" name="delivery">
                        <option value="">{{ __('All') }}</option>
                        @foreach (['pending' => __('Not sent yet'), 'dispatched' => __('On the way'), 'delivered' => __('Delivered')] as $key => $name)
                            <option value="{{ $key }}" @selected(($filters['delivery'] ?? '') === $key)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Customer') }}</th>@if ($view)<th>{{ __('Deliver to') }}</th><th>{{ __('Transport') }}</th>@else<th class="text-end">{{ __('Total') }}</th>@endif<th>{{ __('Status') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($sales as $sale)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('sales.show', ['id' => $sale->id]) }}" class="fw-semibold">{{ $sale->number }}</a></td>
                        <td class="text-nowrap">{{ fmt_date($sale->date) }}</td>
                        <td>{{ $sale->customer->name }}</td>
                        @if ($view)
                            <td>{{ $sale->delivery_address }}</td>
                            <td>{{ $sale->transport_no }}{{ $sale->driver ? ' · '.$sale->driver : '' }}</td>
                        @else
                            <td class="text-end text-nowrap">{{ money($sale->total, $sale->currency->code) }}</td>
                        @endif
                        <td>@include('sales.status')</td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-light-secondary" href="{{ route('sales.show', ['id' => $sale->id]) }}">{{ __('Open') }}</a>
                            <a class="btn btn-sm btn-light-secondary" href="{{ route('sales.print', ['id' => $sale->id] + ($view ? ['paper' => 'delivery'] : [])) }}" target="_blank" rel="noopener">{{ $view ? __('Delivery note') : __('Print') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $sales->links('partials.pagination') }}
    </div>
</div>
@endsection
