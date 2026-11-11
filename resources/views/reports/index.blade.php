@extends('layouts.app', ['title' => __('Reports')])

@php
    $reports = [
        [__('Profit and loss'), __('What we earned and what it cost.'), 'reports.profit', 'chart-line'],
        [__('Who owes us, whom we owe'), __('Customers, suppliers and everyone else with a balance.'), 'reports.balances', 'scale-balanced'],
        [__('Cash flow'), __('Money in and out of every cash box, bank and sarafi.'), 'reports.cash', 'money-bill-wave'],
        [__('Sales report'), __('Sales by product and by customer, with profit.'), 'reports.sales', 'file-invoice-dollar'],
        [__('Purchases report'), __('Purchases by item and by supplier.'), 'reports.purchases', 'cart-flatbed'],
        [__('Balance sheet'), __('What the company has, owes and is worth.'), 'reports.balance', 'building-columns'],
        [__('Trial balance'), __('Every account with its balance. For the accountant.'), 'reports.trial', 'list-check'],
    ];
@endphp

@section('content')
<x-page-header :title="__('Reports')" icon="chart-pie" />

<div class="row g-3">
    @foreach ($reports as [$name, $text, $routeName, $reportIcon])
        <div class="col-12 col-md-6 col-xl-4">
            <a class="card h-100 text-decoration-none" href="{{ route($routeName) }}">
                <div class="card-body d-flex gap-3 align-items-start">
                    <span class="page-header-icon"><x-icon :name="$reportIcon" /></span>
                    <div><h5 class="mb-1">{{ $name }}</h5><p class="text-muted mb-0">{{ $text }}</p></div>
                </div>
            </a>
        </div>
    @endforeach
    <div class="col-12 col-md-6 col-xl-4">
        <a class="card h-100 text-decoration-none" href="{{ route('stock.index') }}">
            <div class="card-body d-flex gap-3 align-items-start">
                <span class="page-header-icon"><x-icon name="boxes-stacked" /></span>
                <div><h5 class="mb-1">{{ __('Stock on hand') }}</h5><p class="text-muted mb-0">{{ __('What is in the warehouses and what it is worth.') }}</p></div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <a class="card h-100 text-decoration-none" href="{{ route('chart') }}">
            <div class="card-body d-flex gap-3 align-items-start">
                <span class="page-header-icon"><x-icon name="sitemap" /></span>
                <div><h5 class="mb-1">{{ __('Account statements') }}</h5><p class="text-muted mb-0">{{ __('Open any account and see every movement.') }}</p></div>
            </div>
        </a>
    </div>
</div>
@endsection
