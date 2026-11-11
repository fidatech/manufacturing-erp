@extends('layouts.app', ['title' => __('Stock card').': '.$item->name])

@section('content')
<x-page-header :title="$item->label()" icon="clipboard-list" :crumbs="[[__('Stock on hand'), route('stock.index')], [__('Stock card')]]">
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3 no-print">
            @include('partials.date-filter')
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1">{{ __('Warehouse') }}</label>
                <select class="form-select" name="warehouse">
                    <option value="">{{ __('All warehouses') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse'] ?? '') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        <table class="table table-hover w-100 keep-table">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('What happened') }}</th><th>{{ __('Warehouse') }}</th><th>{{ __('Batch') }}</th><th class="text-end">{{ __('In') }}</th><th class="text-end">{{ __('Out') }}</th><th class="text-end">{{ __('Left') }}</th></tr></thead>
            <tbody>
                <tr class="text-muted"><td colspan="6">{{ __('In stock before :date', ['date' => fmt_date($from)]) }}</td><td class="text-end">{{ qty($opening) }}</td></tr>
                @foreach ($rows as $row)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($row['movement']->date) }}</td>
                        <td>{{ $names[$row['movement']->type] ?? $row['movement']->type }} {{ $row['movement']->memo }}</td>
                        <td>{{ $row['movement']->warehouse->name }}</td>
                        <td>{{ $row['movement']->batch_no }}@if ($row['movement']->expiry_date) <span class="small text-muted">{{ __('exp.') }} {{ fmt_date($row['movement']->expiry_date) }}</span>@endif</td>
                        <td class="text-end text-nowrap">{{ \App\Support\Num::isZero($row['movement']->qty_in) ? '' : qty($row['movement']->qty_in) }}</td>
                        <td class="text-end text-nowrap">{{ \App\Support\Num::isZero($row['movement']->qty_out) ? '' : qty($row['movement']->qty_out) }}</td>
                        <td class="text-end text-nowrap fw-semibold">{{ qty($row['balance']) }}</td>
                    </tr>
                @endforeach
                <tr class="fw-bold"><td colspan="6">{{ __('In stock on :date', ['date' => fmt_date($to)]) }}</td><td class="text-end text-nowrap">{{ qty($closing) }} {{ $item->unit->symbol ?: $item->unit->name }}</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
