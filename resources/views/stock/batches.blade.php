@extends('layouts.app', ['title' => __('Batches and expiry')])

@section('content')
<x-page-header :title="__('Batches and expiry')" icon="hourglass-half" :crumbs="[[__('Stock on hand'), route('stock.index')], [__('Batches and expiry')]]" :subtitle="__('The earliest expiry is at the top. Sales and transfers take these first by themselves.')" />

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3">
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
                <label class="form-label small mb-1">{{ __('Expires within') }}</label>
                <select class="form-select" name="days">
                    <option value="">{{ __('Show all') }}</option>
                    @foreach ([30 => __('1 month'), 90 => __('3 months'), 180 => __('6 months'), 365 => __('1 year')] as $days => $label)
                        <option value="{{ $days }}" @selected((string) ($filters['days'] ?? '') === (string) $days)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Warehouse') }}</th><th>{{ __('Batch') }}</th><th>{{ __('Made on') }}</th><th>{{ __('Expires on') }}</th><th class="text-end">{{ __('In stock') }}</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $item = $items[$row->item_id];
                        $expiry = $row->expiry_date ? substr((string) $row->expiry_date, 0, 10) : null;
                    @endphp
                    <tr>
                        <td>{{ $item->label() }}</td>
                        <td>{{ $warehouses[$row->warehouse_id]->name ?? '' }}</td>
                        <td>{{ $row->batch_no }}</td>
                        <td>{{ fmt_date($row->mfg_date) }}</td>
                        <td>{{ fmt_date($expiry) }}@if ($expiry && $expiry < $today) <span class="badge-soft danger">{{ __('Expired') }}</span>@endif</td>
                        <td class="text-end text-nowrap">{{ qty($row->qty) }} {{ $item->unit->symbol ?: $item->unit->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('Nothing found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
