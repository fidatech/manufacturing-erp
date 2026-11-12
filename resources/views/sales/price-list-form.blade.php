@extends('layouts.app', ['title' => __('Add :name', ['name' => $singular])])

@section('content')
<x-page-header :title="__('Add :name', ['name' => $singular])" :icon="$icon" :crumbs="[[$title, route('price-lists.index')], [__('Add')]]" />

<form method="POST" action="{{ route('price-lists.store') }}">
    @csrf
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="f-customer">{{ __('Customer') }} <span class="text-danger">*</span></label>
                    <select class="form-select @error('customer_id') is-invalid @enderror" id="f-customer" name="customer_id" required data-search data-party {{ quick_add('accounts', __('New customer'), 'accounts.create', ['type' => 'customer']) }}>
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" data-cur="{{ $customer->currency->code }}" @selected((string) old('customer_id', request('customer')) === (string) $customer->id)>{{ $customer->label() }}</option>
                        @endforeach
                    </select>
                    @error('customer_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <div class="form-text">{{ __('Prices are in the currency of the customer\'s account.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="card-title">{{ __('Items') }}</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table w-100 keep-table" data-rows>
                    <thead><tr><th>{{ __('Item') }}</th><th class="text-end">{{ __('Price for this customer') }}</th><th></th></tr></thead>
                    <tbody data-rows-body="lines">
                        @foreach ($startLines as $i => $line)
                            @include('sales.price-list-row', ['i' => $i, 'line' => $line])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <template data-row-template="lines">@include('sales.price-list-row', ['i' => '__i__', 'line' => []])</template>
            <button type="button" class="btn btn-sm btn-outline-soft" data-add-row="lines"><x-icon name="plus" /> {{ __('Add item') }}</button>
            @can('items.manage')
                <button type="button" class="btn btn-sm btn-outline-soft" data-quick-target="select[data-item]" {{ quick_add('items', __('New product'), 'items.create', ['type' => 'product']) }}><x-icon name="plus" /> {{ __('New product') }}</button>
            @endcan
            <div class="form-text mt-2">{{ __('An item that already has a price for this customer gets the new price.') }}</div>
        </div>
    </div>

    <div class="d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('price-lists.index') }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>
@endsection
