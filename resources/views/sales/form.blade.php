@extends('layouts.app', ['title' => __('Add :name', ['name' => $title])])

@php
    $startLines = old('lines') ?? ($from ? $from->lines->map(fn ($l) => ['item_id' => $l->item_id, 'qty' => $l->qty, 'price' => $l->price])->all() : [[]]);
@endphp

@section('content')
<x-page-header :title="__('Add :name', ['name' => $title])" icon="file-invoice-dollar" :crumbs="[[\App\Http\Controllers\Trade\SaleController::names()[$type], route('sales.index', ['type' => $type])], [__('Add')]]" />

<form method="POST" action="{{ route('sales.store') }}" data-totals data-base="{{ $base }}" data-prices="{{ json_encode($prices) }}">
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">
    @if ($from)
        <input type="hidden" name="parent_id" value="{{ $from->id }}">
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="f-date">{{ __('Date') }} <span class="text-danger">*</span></label>
                    <x-date name="date" :value="old('date', today_iso())" id="f-date" required />
                    @error('date')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label" for="f-customer">{{ __('Customer') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="f-customer" name="customer_id" {{ quick_add('accounts', __('New customer'), 'accounts.create', ['type' => 'customer']) }} required data-search data-party>
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" data-cur="{{ $customer->currency->code }}" @selected((string) old('customer_id', $from?->customer_id ?? request('customer')) === (string) $customer->id)>{{ $customer->name }}{{ $customer->company ? ' · '.$customer->company : '' }} ({{ $customer->currency->code }})</option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ __('Prices are in the currency of the customer\'s account.') }}</div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="f-warehouse">{{ $type === 'return' ? __('Put the goods back in') : __('Take the goods from') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="f-warehouse" name="warehouse_id" {{ quick_add('setup', __('New warehouse'), 'warehouses.create') }} required>
                        @if ($warehouses->count() !== 1)
                            <option value="">{{ __('Choose…') }}</option>
                        @endif
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) old('warehouse_id', $from?->warehouse_id) === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    @if ($warehouses->isEmpty())
                        <div class="form-text text-danger">{{ __('Add a warehouse first (Setup > Warehouses).') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="card-title">{{ __('Items') }}</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table w-100 keep-table" data-rows>
                    <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Total') }}</th><th></th></tr></thead>
                    <tbody data-rows-body="lines">
                        @foreach ($startLines as $i => $line)
                            @include('sales.row', ['i' => $i, 'line' => $line])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <template data-row-template="lines">@include('sales.row', ['i' => '__i__', 'line' => []])</template>
            <button type="button" class="btn btn-sm btn-outline-soft" data-add-row="lines"><x-icon name="plus" /> {{ __('Add item') }}</button>
            @can('items.manage')
                <button type="button" class="btn btn-sm btn-outline-soft" data-quick-target="select[data-item]" {{ quick_add('items', __('New product'), 'items.create', ['type' => 'product']) }}><x-icon name="plus" /> {{ __('New product') }}</button>
            @endcan

            <div class="row justify-content-end mt-3">
                <div class="col-12 col-md-5">
                    <div class="d-flex justify-content-between py-1"><span>{{ __('Total of items') }}</span><strong><span data-subtotal>0.00</span> <span data-party-cur></span></strong></div>
                    <div class="d-flex justify-content-between align-items-center py-1 gap-3"><label for="f-discount">{{ __('Discount') }}</label><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end w-50" id="f-discount" name="discount" value="{{ old('discount') }}" data-discount></div>
                    <div class="d-flex justify-content-between py-1 fs-5"><span>{{ __('Total') }}</span><strong><span data-grand>0.00</span> <span data-party-cur></span></strong></div>
                </div>
            </div>
        </div>
    </div>

    @if ($type === 'invoice')
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="f-received">{{ __('Money received now') }} <span class="text-muted" data-party-cur></span></label>
                        <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control" id="f-received" name="received" value="{{ old('received') }}" data-paid>
                        <div class="form-text">{{ __('Leave empty if the customer pays later.') }} {{ __('Stays on the customer\'s account') }}: <strong data-remaining>0.00</strong></div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="f-received-in">{{ __('Put the money in') }}</label>
                        <select class="form-select" id="f-received-in" name="received_in_id">
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($moneyAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('received_in_id') === (string) $account->id)>{{ $account->name }} ({{ $account->currency->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="f-transport">{{ __('Transport number') }}</label>
                        <input type="text" class="form-control" id="f-transport" name="transport_no" value="{{ old('transport_no') }}" maxlength="100">
                    </div>
                    <div class="col-12 col-md-8">
                        <label class="form-label" for="f-address">{{ __('Deliver to') }}</label>
                        <input type="text" class="form-control" id="f-address" name="delivery_address" value="{{ old('delivery_address') }}" maxlength="255" placeholder="{{ __('Empty = the customer\'s address') }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="f-driver">{{ __('Driver') }}</label>
                        <input type="text" class="form-control" id="f-driver" name="driver" value="{{ old('driver') }}" maxlength="150">
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <label class="form-label" for="f-notes">{{ __('Notes') }}</label>
            <textarea class="form-control" id="f-notes" name="notes" rows="2">{{ old('notes') }}</textarea>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
            <a class="btn btn-light-secondary" href="{{ route('sales.index', ['type' => $type]) }}">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
        </div>
    </div>
</form>
@endsection
