@extends('layouts.app', ['title' => __('Sales report')])

@section('content')
<x-page-header :title="__('Sales report')" icon="file-invoice-dollar" :crumbs="[[__('Reports'), route('reports.index')], [__('Sales report')]]" />

<div class="card">
    <div class="card-body">
        @include('reports.filter', ['heading' => __('Sales report')])
        @foreach ([[__('By product'), $byItem, true], [__('By customer'), $byCustomer, false]] as [$heading, $list, $withQty])
            <h5 class="mt-3">{{ $heading }}</h5>
            <table class="table table-hover w-100 keep-table">
                <thead><tr><th>{{ __('Name') }}</th>@if ($withQty)<th class="text-end">{{ __('Quantity') }}</th>@endif<th class="text-end">{{ __('Sold for') }}</th><th class="text-end">{{ __('Cost') }}</th><th class="text-end">{{ __('Profit') }}</th></tr></thead>
                <tbody>
                    @php
                        $amount = '0';
                        $cost = '0';
                    @endphp
                    @forelse ($list as $row)
                        @php
                            $amount = \App\Support\Num::add($amount, \App\Support\Num::money($row->amount));
                            $cost = \App\Support\Num::add($cost, \App\Support\Num::money($row->cost));
                        @endphp
                        <tr>
                            <td>{{ $row->name }}{{ ! empty($row->code) ? ' ('.$row->code.')' : '' }}</td>
                            @if ($withQty)<td class="text-end">{{ qty(\App\Support\Num::qty($row->qty)) }}</td>@endif
                            <td class="text-end">{{ money(\App\Support\Num::money($row->amount)) }}</td>
                            <td class="text-end">{{ money(\App\Support\Num::money($row->cost)) }}</td>
                            <td class="text-end fw-semibold">{{ money(\App\Support\Num::sub(\App\Support\Num::money($row->amount), \App\Support\Num::money($row->cost))) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">{{ __('No sales in these days.') }}</td></tr>
                    @endforelse
                    <tr class="fw-bold"><td @if ($withQty) colspan="2" @endif>{{ __('Total') }}</td><td class="text-end">{{ money($amount) }}</td><td class="text-end">{{ money($cost) }}</td><td class="text-end">{{ money(\App\Support\Num::sub($amount, $cost), $base) }}</td></tr>
                </tbody>
            </table>
        @endforeach
    </div>
</div>
@endsection
