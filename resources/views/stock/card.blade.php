@extends('layouts.app', ['title' => __('Stock card').': '.$item->name])

@php
    use App\Support\Num;

    $unit = $item->unit->symbol ?: $item->unit->name;
    $tone = fn ($balance) => Num::isNegative($balance) ? 'is-minus' : (Num::isZero($balance) ? 'is-zero' : 'is-plus');
    $periodText = fmt_date($from).' – '.fmt_date($to);
    $place = optional($warehouses->firstWhere('id', (int) ($filters['warehouse'] ?? 0)))->name ?: __('All warehouses');
@endphp

@section('content')
<x-page-header :title="__('Stock card')" icon="clipboard-list" :crumbs="[[__('Stock on hand'), route('stock.index')], [$item->name]]">
    <button type="button" class="btn btn-primary" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
</x-page-header>

<div class="stmt">
    @include('statement.letterhead', ['heading' => __('Stock card'), 'period' => $periodText, 'note' => $place])

    <div class="card stmt-id">
        <div class="card-body">
            <div class="stmt-who">
                <span class="avatar avatar-lg avatar-soft-primary"><x-icon name="box" /></span>
                <div class="stmt-who-text">
                    <h2>{{ $item->name }}</h2>
                    <div class="stmt-tags">
                        <span class="badge-soft primary">{{ $item->type === 'raw' ? __('Raw material') : __('Product') }}</span>
                        @if ($item->code)
                            <span class="badge-soft secondary">{{ $item->code }}</span>
                        @endif
                        @if ($item->packing)
                            <span class="badge-soft secondary">{{ $item->packing }}</span>
                        @endif
                        <span class="badge-soft secondary">{{ $place }}</span>
                    </div>
                </div>
            </div>
            <div class="stmt-now {{ $tone($closing) }}">
                <div class="stmt-now-label">{{ __('In stock on :date', ['date' => fmt_date($to)]) }}</div>
                <div class="stmt-now-value">{{ qty($closing) }} <small>{{ $unit }}</small></div>
            </div>
        </div>
    </div>

    @include('statement.period', ['extra' => 'stock.card-filter'])

    <div class="row g-3 stmt-cards">
        <div class="col-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="sc-top">
                    <div><div class="sc-label">{{ __('In stock before') }}</div><div class="sc-value">{{ qty($opening) }}</div></div>
                    <span class="sc-icon icon-soft info"><x-icon name="hourglass-start" /></span>
                </div>
                <div class="sc-foot">{{ fmt_date($from) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="sc-top">
                    <div><div class="sc-label">{{ __('Came in') }}</div><div class="sc-value">{{ qty($in) }}</div></div>
                    <span class="sc-icon icon-soft success"><x-icon name="arrow-down" /></span>
                </div>
                <div class="sc-foot">{{ $unit }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="sc-top">
                    <div><div class="sc-label">{{ __('Went out') }}</div><div class="sc-value">{{ qty($out) }}</div></div>
                    <span class="sc-icon icon-soft danger"><x-icon name="arrow-up" /></span>
                </div>
                <div class="sc-foot">{{ $unit }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="sc-top">
                    <div><div class="sc-label">{{ __('Left now') }}</div><div class="sc-value">{{ qty($closing) }}</div></div>
                    <span class="sc-icon icon-soft primary"><x-icon name="boxes-stacked" /></span>
                </div>
                <div class="sc-foot">{{ $unit }}</div>
            </div>
        </div>
    </div>

    <div class="card stmt-ledger">
        <div class="stmt-ledger-head">
            <h3>{{ __('Movements') }}</h3>
            <span class="badge-soft secondary">{{ count($rows) }}</span>
            <span class="stmt-ledger-period">{{ $periodText }}</span>
        </div>
        @if ($rows)
            <div class="table-responsive">
                <table class="table stmt-table keep-table w-100">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('What happened') }}</th>
                            <th>{{ __('Batch') }}</th>
                            <th class="text-end">{{ __('In') }}</th>
                            <th class="text-end">{{ __('Out') }}</th>
                            <th class="text-end">{{ __('Left') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="stmt-row-open">
                            <td class="text-nowrap">{{ fmt_date($from) }}</td>
                            <td colspan="4">{{ __('In stock before :date', ['date' => fmt_date($from)]) }}</td>
                            <td data-label="{{ __('Left') }}" class="text-end text-nowrap stmt-bal {{ $tone($opening) }}">{{ qty($opening) }}</td>
                        </tr>
                        @foreach ($rows as $row)
                            @php $movement = $row['movement']; @endphp
                            <tr>
                                <td class="text-nowrap">{{ fmt_date($movement->date) }}</td>
                                <td>
                                    <div class="stmt-what">{{ $movement->memo ?: ($names[$movement->type] ?? $movement->type) }}</div>
                                    <div class="stmt-ref">
                                        <span class="badge-soft {{ \App\Support\EntryType::stockColor($movement->type) }}">{{ $names[$movement->type] ?? $movement->type }}</span>
                                        <span>{{ $movement->warehouse->name }}</span>
                                    </div>
                                </td>
                                <td class="stmt-extra">@if ($movement->batch_no || $movement->expiry_date)<span class="d-sm-none">{{ __('Batch') }} </span>{{ $movement->batch_no }}@if ($movement->expiry_date) <span class="text-nowrap">· {{ __('exp.') }} {{ fmt_date($movement->expiry_date) }}</span>@endif @endif</td>
                                <td class="text-end text-nowrap stmt-in">{{ Num::isZero($movement->qty_in) ? '' : qty($movement->qty_in) }}</td>
                                <td class="text-end text-nowrap stmt-out">{{ Num::isZero($movement->qty_out) ? '' : qty($movement->qty_out) }}</td>
                                <td data-label="{{ __('Left') }}" class="text-end text-nowrap stmt-bal {{ $tone($row['balance']) }}">{{ qty($row['balance']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2">{{ __('Total') }}</td><td class="stmt-extra"></td>
                            <td data-label="{{ __('In') }}" class="text-end text-nowrap">{{ qty($in) }}</td>
                            <td data-label="{{ __('Out') }}" class="text-end text-nowrap">{{ qty($out) }}</td>
                            <td data-label="{{ __('Left') }}" class="text-end text-nowrap stmt-bal {{ $tone($closing) }}">{{ qty($closing) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="empty-state">
                <div class="es-icon"><x-icon name="inbox" /></div>
                <h3>{{ __('Nothing happened in these dates') }}</h3>
                <p>{{ __('Choose other dates above, or press "All time" to see everything.') }}</p>
            </div>
        @endif
        <div class="stmt-close">
            <span>{{ __('In stock on :date', ['date' => fmt_date($to)]) }}</span>
            <strong>{{ qty($closing) }} {{ $unit }}</strong>
        </div>
    </div>

    @include('statement.signatures', ['third' => __('Storekeeper')])
</div>
@endsection
