@extends('layouts.app', ['title' => __('Stock')])

@section('content')
<x-page-header :title="__('Stock on hand')" icon="boxes-stacked" :subtitle="__('What we have now and what it is worth.')">
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
    <a class="btn btn-outline-soft" href="{{ route('stock.batches') }}">{{ __('Batches and expiry') }}</a>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3 no-print">
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1">{{ __('Warehouse') }}</label>
                <select class="form-select" name="warehouse">
                    <option value="">{{ __('All warehouses') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse'] ?? '') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1">{{ __('Kind') }}</label>
                <select class="form-select" name="type">
                    <option value="">{{ __('All') }}</option>
                    <option value="product" @selected(($filters['type'] ?? '') === 'product')>{{ __('Products') }}</option>
                    <option value="raw" @selected(($filters['type'] ?? '') === 'raw')>{{ __('Raw materials') }}</option>
                </select>
            </div>
            <div class="col">
                <label class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}">
            </div>
            <div class="col-auto">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="f-low" name="low" value="1" @checked(request()->boolean('low'))>
                    <label class="form-check-label" for="f-low">{{ __('Running low only') }}</label>
                </div>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Kind') }}</th><th class="text-end">{{ __('In stock') }}</th><th class="text-end">{{ __('Average cost') }}</th><th class="text-end">{{ __('Worth (:base)', ['base' => $base]) }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['item']->label() }}@if ($row['low']) <span class="badge-soft danger">{{ __('Low') }}</span>@endif</td>
                        <td>{{ $row['item']->type === 'raw' ? __('Raw material') : __('Product') }}{{ $row['item']->category ? ' · '.$row['item']->category->name : '' }}</td>
                        <td class="text-end text-nowrap fw-semibold">{{ qty($row['qty']) }} {{ $row['item']->unit->symbol ?: $row['item']->unit->name }}</td>
                        <td class="text-end text-nowrap">{{ \App\Support\Num::format($row['item']->avg_cost, 4, true) }}</td>
                        <td class="text-end text-nowrap">{{ money($row['value']) }}</td>
                        <td class="text-end no-print"><a class="btn btn-sm btn-light-secondary" href="{{ route('stock.card', ['id' => $row['item']->id]) }}">{{ __('Stock card') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('Nothing found') }}</td></tr>
                @endforelse
                <tr class="fw-bold"><td colspan="4">{{ __('Total') }}</td><td class="text-end text-nowrap">{{ money($value, $base) }}</td><td class="no-print"></td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
