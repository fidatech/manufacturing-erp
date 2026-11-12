@extends('layouts.app', ['title' => $title])

@section('content')
<x-page-header :title="$title" icon="file-invoice-dollar" :crumbs="[[\App\Http\Controllers\Trade\SaleController::names()[$sale->type], route('sales.index', ['type' => $sale->type])], [$sale->number]]">
    <a class="btn btn-primary" href="{{ route('sales.print', ['id' => $sale->id]) }}" target="_blank" rel="noopener"><x-icon name="print" class="me-1" /> {{ __('Print bill') }}</a>
    @if ($sale->type === 'invoice')
        <a class="btn btn-outline-soft" href="{{ route('sales.print', ['id' => $sale->id, 'paper' => 'delivery']) }}" target="_blank" rel="noopener">{{ __('Delivery note') }}</a>
    @endif
    @can('sales.manage')
        @if ($sale->type === 'quotation' && $sale->status === 'open')
            <a class="btn btn-outline-soft" href="{{ route('sales.create', ['type' => 'order', 'from' => $sale->id]) }}">{{ __('Turn into an order') }}</a>
        @endif
        @if (in_array($sale->type, ['order', 'quotation'], true) && $sale->status === 'open')
            <a class="btn btn-success" href="{{ route('sales.create', ['type' => 'invoice', 'from' => $sale->id]) }}">{{ __('Turn into a sale') }}</a>
        @endif
        @if ($sale->type === 'invoice' && $sale->status === 'posted')
            <a class="btn btn-outline-soft" href="{{ route('sales.create', ['type' => 'return', 'from' => $sale->id]) }}">{{ __('Goods came back') }}</a>
        @endif
        @if ($sale->status !== 'cancelled')
            <form method="POST" action="{{ route('sales.cancel', ['id' => $sale->id]) }}" class="d-inline" data-confirm="{{ __('Cancel :number?', ['number' => $sale->number]) }}" data-confirm-text="{{ __('Its stock and money entries will no longer count.') }}" data-confirm-ok="{{ __('Cancel it') }}">
                @csrf
                <button type="submit" class="btn btn-light-danger">{{ __('Cancel') }}</button>
            </form>
        @endif
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Date') }}</div><strong>{{ fmt_date($sale->date) }}</strong></div>
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Customer') }}</div><strong><a href="{{ route('statement', ['id' => $sale->customer_id]) }}">{{ $sale->customer->name }}</a></strong></div>
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Warehouse') }}</div><strong>{{ $sale->warehouse->name }}</strong></div>
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Status') }}</div>@include('sales.status')</div>
            @if ($sale->parent)
                <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Made from') }}</div><strong><a href="{{ route('sales.show', ['id' => $sale->parent_id]) }}">{{ $sale->parent->number }}</a></strong></div>
            @endif
        </div>

        <table class="table w-100 keep-table">
            <thead><tr><th>#</th><th>{{ __('Item') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
            <tbody>
                @foreach ($sale->lines as $line)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $line->item->label() }}</td>
                        <td class="text-end text-nowrap">{{ qty($line->qty) }} {{ $line->item->unit->symbol }}</td>
                        <td class="text-end text-nowrap">{{ \App\Support\Num::isZero($line->price) ? __('free') : \App\Support\Num::format($line->price, 6, true) }}</td>
                        <td class="text-end text-nowrap">{{ money($line->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="row justify-content-end">
            <div class="col-12 col-md-5">
                @unless (\App\Support\Num::isZero($sale->discount))
                    <div class="d-flex justify-content-between py-1"><span>{{ __('Total of items') }}</span><strong>{{ money($sale->subtotal, $sale->currency->code) }}</strong></div>
                    <div class="d-flex justify-content-between py-1"><span>{{ __('Discount') }}</span><strong>{{ money($sale->discount, $sale->currency->code) }}</strong></div>
                @endunless
                <div class="d-flex justify-content-between py-1 fs-5"><span>{{ __('Total') }}</span><strong>{{ money($sale->total, $sale->currency->code) }}</strong></div>
                @if ($sale->type === 'invoice')
                    <div class="d-flex justify-content-between py-1"><span>{{ __('Received') }}{{ $sale->receivedIn ? ' · '.$sale->receivedIn->name : '' }}</span><strong>{{ money($sale->received, $sale->currency->code) }}</strong></div>
                    <div class="d-flex justify-content-between py-1 text-muted"><span>{{ __('Customer\'s balance today') }}</span><span>{{ $sale->customer->balanceHtml($balance) }}</span></div>
                @endif
            </div>
        </div>
        @if ($sale->notes)
            <p class="mt-3 mb-0"><span class="text-muted">{{ __('Notes') }}:</span> {{ $sale->notes }}</p>
        @endif
    </div>
</div>

@if ($sale->type === 'invoice' && $sale->status === 'posted')
    <div class="card mt-4 no-print">
        <div class="card-header"><h5 class="card-title">{{ __('Delivery') }}</h5></div>
        <div class="card-body">
            <p class="mb-3">
                @include('sales.status')
                @if ($sale->dispatched_date) · {{ __('Sent on') }} {{ fmt_date($sale->dispatched_date) }} @endif
                @if ($sale->delivered_date) · {{ __('Delivered on') }} {{ fmt_date($sale->delivered_date) }} @endif
            </p>
            @can('sales.manage')
                <form method="POST" action="{{ route('sales.delivery', ['id' => $sale->id]) }}" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="f-status">{{ __('Where are the goods?') }}</label>
                        <select class="form-select" id="f-status" name="delivery_status">
                            @foreach (['pending' => __('Not sent yet'), 'dispatched' => __('On the way'), 'delivered' => __('Delivered')] as $key => $name)
                                <option value="{{ $key }}" @selected($sale->delivery_status === $key)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2"><label class="form-label">{{ __('Date') }}</label><x-date name="date" :value="today_iso()" /></div>
                    <div class="col-6 col-md-2"><label class="form-label" for="f-transport">{{ __('Transport number') }}</label><input type="text" class="form-control" id="f-transport" name="transport_no" value="{{ $sale->transport_no }}"></div>
                    <div class="col-6 col-md-2"><label class="form-label" for="f-driver">{{ __('Driver') }}</label><input type="text" class="form-control" id="f-driver" name="driver" value="{{ $sale->driver }}"></div>
                    <div class="col-6 col-md-3"><label class="form-label" for="f-address">{{ __('Deliver to') }}</label><input type="text" class="form-control" id="f-address" name="delivery_address" value="{{ $sale->delivery_address }}"></div>
                    <div class="col-12"><button type="submit" class="btn btn-primary">{{ __('Save delivery') }}</button></div>
                </form>
            @endcan
        </div>
    </div>
@endif

@include('partials.attachments', ['subjectType' => 'Sale', 'subjectId' => $sale->id])
@endsection
