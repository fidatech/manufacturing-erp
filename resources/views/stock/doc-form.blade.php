@extends('layouts.app', ['title' => $title])

@php
    $help = [
        'opening' => __('Write what is in the warehouse today and what each one cost. Do this once, when you start using the system.'),
        'transfer' => __('Move goods from one warehouse to another. The oldest expiry is moved first.'),
        'count' => __('Count the goods in the warehouse and write what you really found. The system corrects itself to your count.'),
    ][$type];
    $startLines = old('lines');
    if ($startLines === null) {
        $startLines = [];
        foreach ($system as $itemId => $qty) {
            if (\App\Support\Num::isPositive($qty)) {
                $startLines[] = ['item_id' => $itemId, 'system' => $qty, 'qty' => ''];
            }
        }
        $startLines = $startLines ?: [[]];
    }
@endphp

@section('content')
<x-page-header :title="$title" icon="right-left" :crumbs="[[\App\Http\Controllers\Inventory\StockDocController::names()[$type], route('stock-docs.index', ['type' => $type])], [__('Add')]]" :subtitle="$help" />

@if ($type === 'count' && ! $warehouseId)
    <form method="GET" class="card">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="f-pick">{{ __('Which warehouse are you counting?') }}</label>
                    <select class="form-select" id="f-pick" name="warehouse" required>
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-primary">{{ __('Start counting') }}</button></div>
            </div>
        </div>
    </form>
@else
    <form method="POST" action="{{ route('stock-docs.store', ['type' => $type]) }}" class="card">
        @csrf
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="f-date">{{ __('Date') }} <span class="text-danger">*</span></label>
                    <x-date name="date" :value="old('date', today_iso())" id="f-date" required />
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="f-warehouse">{{ $type === 'transfer' ? __('From warehouse') : __('Warehouse') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="f-warehouse" name="warehouse_id" required>
                        @if ($warehouses->count() !== 1)
                            <option value="">{{ __('Choose…') }}</option>
                        @endif
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id', $warehouseId ?: '') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($type === 'transfer')
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="f-to">{{ __('To warehouse') }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="f-to" name="to_warehouse_id" required>
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((string) old('to_warehouse_id') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table w-100 keep-table" data-rows>
                    <thead>
                        <tr>
                            <th>{{ __('Item') }}</th>
                            @if ($type === 'count')
                                <th class="text-end">{{ __('System says') }}</th>
                            @endif
                            <th class="text-end">{{ $type === 'count' ? __('I counted') : __('Quantity') }}</th>
                            @if ($type === 'opening')
                                <th class="text-end">{{ __('Cost each (:base)', ['base' => $base]) }}</th><th>{{ __('Batch') }}</th><th>{{ __('Made on') }}</th><th>{{ __('Expires on') }}</th>
                            @endif
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-rows-body>
                        @foreach ($startLines as $i => $line)
                            @include('stock.doc-row', ['i' => $i, 'line' => $line])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <template data-row-template>@include('stock.doc-row', ['i' => '__i__', 'line' => []])</template>
            <button type="button" class="btn btn-sm btn-outline-soft" data-add-row><x-icon name="plus" /> {{ __('Add item') }}</button>
            @if ($type === 'count')
                <div class="form-text">{{ __('Rows you leave empty are not changed.') }}</div>
            @endif

            <div class="mt-3">
                <label class="form-label" for="f-notes">{{ __('Notes') }}</label>
                <textarea class="form-control" id="f-notes" name="notes" rows="2">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
            <a class="btn btn-light-secondary" href="{{ route('stock-docs.index', ['type' => $type]) }}">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
        </div>
    </form>
@endif
@endsection
