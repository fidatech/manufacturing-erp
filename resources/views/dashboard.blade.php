@extends('layouts.app', ['title' => __('app.dashboard.title')])

@php
    $actions = [
        ['sales.manage', __('New sale'), route('sales.create', ['type' => 'invoice']), 'file-invoice-dollar', 'btn-primary'],
        ['purchases.manage', __('New purchase'), route('purchases.create', ['type' => 'invoice']), 'cart-flatbed', 'btn-outline-soft'],
        ['money.manage', __('Money in'), route('money.create', ['kind' => 'in']), 'arrow-down', 'btn-success'],
        ['money.manage', __('Money out'), route('money.create', ['kind' => 'out']), 'arrow-up', 'btn-danger'],
        ['money.manage', __('Expense'), route('money.create', ['kind' => 'expense']), 'receipt', 'btn-warning'],
    ];
@endphp

@section('content')
<x-page-header :title="__('app.dashboard.title')" icon="gauge-high" :subtitle="__('app.dashboard.empty_title', ['name' => auth()->user()->name]).' · '.fmt_date(today_iso())" />

<div class="d-flex flex-wrap gap-2 mb-4">
    @foreach ($actions as [$permission, $label, $url, $actionIcon, $style])
        @can($permission)
            <a class="btn btn-lg {{ $style }}" href="{{ $url }}"><x-icon :name="$actionIcon" class="me-1" /> {{ $label }}</a>
        @endcan
    @endforeach
</div>

<div class="row g-3 mb-4">
    @if ($sales)
        <div class="col-6 col-xl-3"><div class="card stat-card mb-0"><div class="sc-top"><div><div class="sc-label">{{ __('Sales today') }}</div><div class="sc-value">{{ money($sales['today']) }}</div></div><span class="sc-icon icon-soft primary"><x-icon name="file-invoice-dollar" /></span></div><div class="sc-foot">{{ __('This month') }}: {{ money($sales['month'], $base) }}</div></div></div>
    @endif
    @if ($owed)
        <div class="col-6 col-xl-3"><div class="card stat-card mb-0"><div class="sc-top"><div><div class="sc-label">{{ __('Others owe us') }}</div><div class="sc-value">{{ money($owed['us']) }}</div></div><span class="sc-icon icon-soft success"><x-icon name="hand-holding-dollar" /></span></div><div class="sc-foot">@can('reports.view')<a href="{{ route('reports.balances') }}">{{ __('See who') }}</a>@endcan {{ $base }}</div></div></div>
        <div class="col-6 col-xl-3"><div class="card stat-card mb-0"><div class="sc-top"><div><div class="sc-label">{{ __('We owe others') }}</div><div class="sc-value">{{ money($owed['them']) }}</div></div><span class="sc-icon icon-soft danger"><x-icon name="scale-balanced" /></span></div><div class="sc-foot">@can('reports.view')<a href="{{ route('reports.balances') }}">{{ __('See who') }}</a>@endcan {{ $base }}</div></div></div>
    @endif
    @if ($stock !== null)
        <div class="col-6 col-xl-3"><div class="card stat-card mb-0"><div class="sc-top"><div><div class="sc-label">{{ __('Stock is worth') }}</div><div class="sc-value">{{ money($stock) }}</div></div><span class="sc-icon icon-soft warning"><x-icon name="boxes-stacked" /></span></div><div class="sc-foot"><a href="{{ route('stock.index') }}">{{ __('Stock on hand') }}</a> {{ $base }}</div></div></div>
    @endif
</div>

<div class="row g-4">
    @if ($money !== null)
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title">{{ __('Money we have') }}</h5></div>
                <div class="card-body">
                    <table class="table w-100 keep-table mb-0">
                        <tbody>
                            @forelse ($money as $row)
                                <tr><td>@can('accounts.view')<a href="{{ route('statement', ['id' => $row['account']->id]) }}">{{ $row['account']->name }}</a>@else{{ $row['account']->name }}@endcan</td><td class="text-end fw-semibold text-nowrap {{ \App\Support\Num::isNegative($row['balance']) ? 'text-danger' : '' }}">{{ money($row['balance'], $row['account']->currency->code) }}</td></tr>
                            @empty
                                <tr><td class="text-muted">{{ __('Nothing here yet') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($low)
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title">{{ __('Running low') }}</h5></div>
                <div class="card-body">
                    <table class="table w-100 keep-table mb-0">
                        <tbody>
                            @foreach ($low as $row)
                                <tr><td><a href="{{ route('stock.card', ['id' => $row['item']->id]) }}">{{ $row['item']->label() }}</a></td><td class="text-end text-danger fw-semibold text-nowrap">{{ qty($row['qty']) }} {{ $row['item']->unit->symbol }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($toDeliver->isNotEmpty())
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title">{{ __('Sales still to deliver') }}</h5></div>
                <div class="card-body">
                    <table class="table w-100 keep-table mb-0">
                        <tbody>
                            @foreach ($toDeliver as $sale)
                                <tr><td><a href="{{ route('sales.show', ['id' => $sale->id]) }}">{{ $sale->number }}</a> · {{ $sale->customer->name }}</td><td class="text-end text-nowrap">{{ fmt_date($sale->date) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($waiting->isNotEmpty())
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title">{{ __('Goods on the way') }}</h5></div>
                <div class="card-body">
                    <table class="table w-100 keep-table mb-0">
                        <tbody>
                            @foreach ($waiting as $purchase)
                                <tr><td><a href="{{ route('purchases.show', ['id' => $purchase->id]) }}">{{ $purchase->number }}</a> · {{ $purchase->supplier->name }}</td><td class="text-end text-nowrap">{{ fmt_date($purchase->date) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($expiring->isNotEmpty())
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title">{{ __('Expiring within two months') }}</h5></div>
                <div class="card-body">
                    <table class="table w-100 keep-table mb-0">
                        <tbody>
                            @foreach ($expiring as $row)
                                <tr><td>{{ $expiringItems[$row->item_id]->name ?? '' }} <span class="text-muted">{{ $row->batch_no }}</span></td><td class="text-end text-nowrap">{{ qty($row->qty) }}</td><td class="text-end text-danger text-nowrap">{{ fmt_date(substr((string) $row->expiry_date, 0, 10)) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
