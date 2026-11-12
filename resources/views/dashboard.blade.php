@extends('layouts.app', ['title' => __('app.dashboard.title')])

@php
    use App\Support\Chart;
    use App\Support\Num;

    $short = fn ($date) => substr(fmt_date($date), 5);
    $actions = [
        ['sales.manage', __('New sale'), route('sales.create', ['type' => 'invoice']), 'file-invoice-dollar', 'btn-primary'],
        ['production.manage', __('Record production'), route('production.create'), 'industry', 'btn-outline-soft'],
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

<div class="row g-4 mb-4">
    @if ($daily)
        <div class="col-12 col-xl-8">
            <div class="card h-100 dash-card">
                <div class="dash-head">
                    <div>
                        <h5>{{ __('Sales in the last 30 days') }}</h5>
                        <p>{{ __('Each bar is one day') }} · {{ $base }}</p>
                    </div>
                    <div class="dash-figure">{{ money($daily['total']) }} <small>{{ $base }}</small></div>
                </div>
                <div class="chart">
                    <div class="chart-plot">
                        <div class="chart-grid">
                            @foreach ($daily['scale']['ticks'] as $tick)
                                <span><em>{{ $tick }}</em></span>
                            @endforeach
                        </div>
                        <div class="chart-cols is-thin">
                            @foreach ($daily['days'] as $day)
                                <div class="chart-col {{ $loop->index < 8 ? 'tip-start' : ($loop->remaining < 8 ? 'tip-end' : '') }}" tabindex="0">
                                    <i class="chart-bar s1" style="height: {{ Chart::percent($day['value'], $daily['scale']['max']) }}%"></i>
                                    <span class="chart-tip"><b>{{ fmt_date($day['date']) }}</b>{{ money($day['value'], $base) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="chart-x is-thin">
                        @foreach ($daily['days'] as $day)
                            <span>{{ $loop->index % 5 === 4 ? $short($day['date']) : '' }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($money !== null)
        <div class="col-12 {{ $daily ? 'col-xl-4' : 'col-lg-6' }}">
            <div class="card h-100 dash-card">
                <div class="dash-head">
                    <div>
                        <h5>{{ __('Money we have') }}</h5>
                        <p>{{ __('Cash boxes, banks and sarafi') }}</p>
                    </div>
                    <div class="dash-figure">{{ money($moneyTotal) }} <small>{{ $base }}</small></div>
                </div>
                <div class="hbars">
                    @forelse ($money as $row)
                        <div class="hbar">
                            <div class="hbar-head">
                                @can('accounts.view')
                                    <a href="{{ route('statement', ['id' => $row['account']->id]) }}">{{ $row['account']->name }}</a>
                                @else
                                    <span>{{ $row['account']->name }}</span>
                                @endcan
                                <strong class="{{ Num::isNegative($row['balance']) ? 'text-danger' : '' }}">{{ money($row['balance'], $row['account']->currency->code) }}</strong>
                            </div>
                            <div class="hbar-track"><i class="{{ Num::isNegative($row['base']) ? 'is-minus' : '' }}" style="width: {{ Chart::percent(Num::abs($row['base']), $moneyMax) }}%"></i></div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">{{ __('Nothing here yet') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    @if ($monthly)
        <div class="col-12 col-xl-7">
            <div class="card h-100 dash-card">
                <div class="dash-head">
                    <div>
                        <h5>{{ __('Sales and purchases, last 6 months') }}</h5>
                        <p>{{ __('Amounts in :base', ['base' => $base]) }}</p>
                    </div>
                    <div class="chart-legend">
                        @if ($monthly['months'][0]['sold'] !== null)
                            <span><i class="s1"></i>{{ __('Sales') }}</span>
                        @endif
                        @if ($monthly['months'][0]['bought'] !== null)
                            <span><i class="s2"></i>{{ __('Purchases') }}</span>
                        @endif
                    </div>
                </div>
                <div class="chart">
                    <div class="chart-plot">
                        <div class="chart-grid">
                            @foreach ($monthly['scale']['ticks'] as $tick)
                                <span><em>{{ $tick }}</em></span>
                            @endforeach
                        </div>
                        <div class="chart-cols">
                            @foreach ($monthly['months'] as $month)
                                <div class="chart-col {{ $loop->first ? 'tip-start' : ($loop->last ? 'tip-end' : '') }}" tabindex="0">
                                    @if ($month['sold'] !== null)
                                        <i class="chart-bar s1" style="height: {{ Chart::percent($month['sold'], $monthly['scale']['max']) }}%"></i>
                                    @endif
                                    @if ($month['bought'] !== null)
                                        <i class="chart-bar s2" style="height: {{ Chart::percent($month['bought'], $monthly['scale']['max']) }}%"></i>
                                    @endif
                                    <span class="chart-tip">
                                        <b>{{ $month['label'] }}</b>
                                        @if ($month['sold'] !== null)
                                            <span><i class="s1"></i>{{ __('Sales') }} {{ money($month['sold']) }}</span>
                                        @endif
                                        @if ($month['bought'] !== null)
                                            <span><i class="s2"></i>{{ __('Purchases') }} {{ money($month['bought']) }}</span>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="chart-x">
                        @foreach ($monthly['months'] as $month)
                            <span>{{ $month['label'] }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($top !== null)
        <div class="col-12 col-xl-5">
            <div class="card h-100 dash-card">
                <div class="dash-head">
                    <div>
                        <h5>{{ __('Best sellers this month') }}</h5>
                        <p>{{ __('Amounts in :base', ['base' => $base]) }}</p>
                    </div>
                </div>
                <div class="hbars">
                    @forelse ($top as $row)
                        <div class="hbar">
                            <div class="hbar-head">
                                @can('inventory.view')
                                    <a href="{{ route('stock.card', ['id' => $row['id']]) }}">{{ $row['name'] }}</a>
                                @else
                                    <span>{{ $row['name'] }}</span>
                                @endcan
                                <strong>{{ money($row['total']) }}</strong>
                            </div>
                            <div class="hbar-track"><i style="width: {{ Chart::percent($row['total'], $top[0]['total']) }}%"></i></div>
                        </div>
                    @empty
                        <div class="empty-state py-4">
                            <div class="es-icon"><x-icon name="chart-simple" /></div>
                            <p>{{ __('No sales yet this month') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>

<div class="row g-4">
    @if ($low)
        <div class="col-12 col-lg-6">
            <div class="card h-100 dash-card">
                <div class="dash-list-head"><span class="sc-icon icon-soft danger"><x-icon name="triangle-exclamation" /></span><h5>{{ __('Running low') }}</h5><span class="badge-soft secondary">{{ count($low) }}</span></div>
                <div class="card-body">
                    <table class="table w-100 keep-table mb-0 dash-table">
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
            <div class="card h-100 dash-card">
                <div class="dash-list-head"><span class="sc-icon icon-soft primary"><x-icon name="truck" /></span><h5>{{ __('Sales still to deliver') }}</h5><span class="badge-soft secondary">{{ $toDeliver->count() }}</span></div>
                <div class="card-body">
                    <table class="table w-100 keep-table mb-0 dash-table">
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
            <div class="card h-100 dash-card">
                <div class="dash-list-head"><span class="sc-icon icon-soft info"><x-icon name="cart-flatbed" /></span><h5>{{ __('Goods on the way') }}</h5><span class="badge-soft secondary">{{ $waiting->count() }}</span></div>
                <div class="card-body">
                    <table class="table w-100 keep-table mb-0 dash-table">
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

    @if ($toTest->isNotEmpty())
        <div class="col-12 col-lg-6">
            <div class="card h-100 dash-card">
                <div class="dash-list-head"><span class="sc-icon icon-soft purple"><x-icon name="flask" /></span><h5>{{ __('Batches for the laboratory') }}</h5><span class="badge-soft secondary">{{ $toTest->count() }}</span></div>
                <div class="card-body">
                    <table class="table w-100 keep-table mb-0 dash-table">
                        <tbody>
                            @foreach ($toTest as $batch)
                                <tr><td><a href="{{ route('production.show', ['id' => $batch->id]) }}">{{ $batch->batch_no }}</a> · {{ $batch->item->name }}</td><td class="text-end">@include('production.qc')</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($expiring->isNotEmpty())
        <div class="col-12 col-lg-6">
            <div class="card h-100 dash-card">
                <div class="dash-list-head"><span class="sc-icon icon-soft warning"><x-icon name="hourglass-half" /></span><h5>{{ __('Expiring within two months') }}</h5><span class="badge-soft secondary">{{ $expiring->count() }}</span></div>
                <div class="card-body">
                    <table class="table w-100 keep-table mb-0 dash-table">
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
