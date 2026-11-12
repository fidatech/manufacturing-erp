@extends('layouts.app', ['title' => __('Add :name', ['name' => $title])])

@php
    // rows to start with: what the user typed, or the paper this one is made from, or one empty row
    $startLines = old('lines') ?? ($from ? $from->lines->map(fn ($l) => ['item_id' => $l->item_id, 'qty' => $l->qty, 'price' => $l->price, 'batch_no' => $l->batch_no])->all() : [[]]);
    $startCosts = old('costs', [[]]);
@endphp

@section('content')
<x-page-header :title="__('Add :name', ['name' => $title])" icon="cart-flatbed" :crumbs="[[\App\Http\Controllers\Trade\PurchaseController::names()[$type], route('purchases.index', ['type' => $type])], [__('Add')]]" />

<form method="POST" action="{{ route('purchases.store') }}" data-totals data-base="{{ $base }}">
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
                    <label class="form-label" for="f-supplier">{{ __('Supplier') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="f-supplier" name="supplier_id" {{ quick_add('accounts', __('New supplier'), 'accounts.create', ['type' => 'supplier']) }} required data-search data-party>
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" data-cur="{{ $supplier->currency->code }}" @selected((string) old('supplier_id', $from?->supplier_id) === (string) $supplier->id)>{{ $supplier->name }} ({{ $supplier->currency->code }})</option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ __('Prices are in the currency of the supplier\'s account.') }}</div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="f-warehouse">{{ __('Warehouse') }} <span class="text-danger">*</span></label>
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
                @if ($type === 'invoice')
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="f-inv">{{ __('Supplier\'s bill number') }}</label>
                        <input type="text" class="form-control" id="f-inv" name="supplier_invoice_no" value="{{ old('supplier_invoice_no') }}" maxlength="60">
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h5 class="card-title">{{ __('Items') }}</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table w-100 keep-table" data-rows>
                    <thead>
                        <tr>
                            <th>{{ __('Item') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Total') }}</th>
                            @if (in_array($type, ['invoice', 'return'], true))
                                <th>{{ __('Batch') }}</th>
                                @if ($type === 'invoice')
                                    <th>{{ __('Made on') }}</th><th>{{ __('Expires on') }}</th>
                                @endif
                            @endif
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-rows-body="lines">
                        @foreach ($startLines as $i => $line)
                            @include('purchases.row', ['i' => $i, 'line' => $line])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <template data-row-template="lines">@include('purchases.row', ['i' => '__i__', 'line' => []])</template>
            <button type="button" class="btn btn-sm btn-outline-soft" data-add-row="lines"><x-icon name="plus" /> {{ __('Add item') }}</button>
            @can('items.manage')
                <button type="button" class="btn btn-sm btn-outline-soft" data-quick-target="select[data-item]" {{ quick_add('items', __('New raw material'), 'items.create', ['type' => 'raw']) }}><x-icon name="plus" /> {{ __('New raw material') }}</button>
                <button type="button" class="btn btn-sm btn-outline-soft" data-quick-target="select[data-item]" {{ quick_add('items', __('New product'), 'items.create', ['type' => 'product']) }}><x-icon name="plus" /> {{ __('New product') }}</button>
            @endcan

            <div class="row justify-content-end mt-3">
                <div class="col-12 col-md-5">
                    <div class="d-flex justify-content-between py-1"><span>{{ __('Total of items') }}</span><strong><span data-subtotal>0.00</span> <span data-party-cur></span></strong></div>
                    <div class="d-flex justify-content-between align-items-center py-1 gap-3"><label for="f-discount">{{ __('Discount') }}</label><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end w-50" id="f-discount" name="discount" value="{{ old('discount') }}" data-discount></div>
                    <div class="d-flex justify-content-between py-1 fs-5"><span>{{ __('To pay the supplier') }}</span><strong><span data-grand>0.00</span> <span data-party-cur></span></strong></div>
                </div>
            </div>
        </div>
    </div>

    @if ($type === 'invoice')
        <div class="card mb-4">
            <div class="card-header"><div><h5 class="card-title">{{ __('Other costs of this purchase') }}</h5><div class="small text-muted">{{ __('Transport, customs and similar. They are added to the cost of the goods.') }}</div></div></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table w-100 keep-table" data-rows>
                        <thead><tr><th>{{ __('Cost') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Paid from') }}</th><th></th></tr></thead>
                        <tbody data-rows-body="costs">
                            @foreach ($startCosts as $i => $cost)
                                @include('purchases.cost-row', ['i' => $i, 'cost' => $cost])
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <template data-row-template="costs">@include('purchases.cost-row', ['i' => '__i__', 'cost' => []])</template>
                <button type="button" class="btn btn-sm btn-outline-soft" data-add-row="costs"><x-icon name="plus" /> {{ __('Add cost') }}</button>
                <div class="form-text">{{ __('The amount is in the currency of the account it was paid from.') }}</div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="f-paid">{{ __('Paid to the supplier now') }} <span class="text-muted" data-party-cur></span></label>
                        <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control" id="f-paid" name="paid" value="{{ old('paid') }}" data-paid>
                        <div class="form-text">{{ __('Leave empty if nothing was paid yet.') }} {{ __('Still to pay') }}: <strong data-remaining>0.00</strong></div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="f-paid-from">{{ __('Paid from') }}</label>
                        <select class="form-select" id="f-paid-from" name="paid_from_id">
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($moneyAccounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('paid_from_id') === (string) $account->id)>{{ $account->name }} ({{ $account->currency->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-check form-switch mt-md-4 pt-md-2">
                            <input type="hidden" name="received" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="f-received" name="received" value="1" @checked(old('received', '1'))>
                            <label class="form-check-label" for="f-received">{{ __('The goods have arrived in the warehouse') }}</label>
                        </div>
                        <div class="form-text">{{ __('Switch off when the goods are still on the way.') }}</div>
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
            <a class="btn btn-light-secondary" href="{{ route('purchases.index', ['type' => $type]) }}">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
        </div>
    </div>
</form>
@endsection
