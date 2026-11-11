@extends('layouts.app', ['title' => __('Purchases report')])

@section('content')
<x-page-header :title="__('Purchases report')" icon="cart-flatbed" :crumbs="[[__('Reports'), route('reports.index')], [__('Purchases report')]]" />

<div class="card">
    <div class="card-body">
        @include('reports.filter')
        @foreach ([[__('By item'), $byItem, true], [__('By supplier'), $bySupplier, false]] as [$heading, $list, $withQty])
            <h5 class="mt-3">{{ $heading }}</h5>
            <table class="table table-hover w-100 keep-table">
                <thead><tr><th>{{ __('Name') }}</th>@if ($withQty)<th class="text-end">{{ __('Quantity') }}</th>@endif<th class="text-end">{{ __('Bought for') }}</th></tr></thead>
                <tbody>
                    @php
                        $amount = '0';
                    @endphp
                    @forelse ($list as $row)
                        @php
                            $amount = \App\Support\Num::add($amount, \App\Support\Num::money($row->amount));
                        @endphp
                        <tr>
                            <td>{{ $row->name }}{{ ! empty($row->code) ? ' ('.$row->code.')' : '' }}</td>
                            @if ($withQty)<td class="text-end">{{ qty(\App\Support\Num::qty($row->qty)) }}</td>@endif
                            <td class="text-end">{{ money(\App\Support\Num::money($row->amount)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">{{ __('No purchases in these days.') }}</td></tr>
                    @endforelse
                    <tr class="fw-bold"><td @if ($withQty) colspan="2" @endif>{{ __('Total') }}</td><td class="text-end">{{ money($amount, $base) }}</td></tr>
                </tbody>
            </table>
        @endforeach
    </div>
</div>
@endsection
