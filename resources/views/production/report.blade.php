@extends('layouts.app', ['title' => __('Production Report')])

@section('content')
<x-page-header :title="__('Production Report')" icon="chart-column" :crumbs="[[__('Production Batches'), route('production.index')], [__('Report')]]" />

<div class="card">
    <div class="card-body">
        @include('reports.filter')
        <h5 class="mt-3">{{ __('What was made') }}</h5>
        <table class="table table-hover w-100 keep-table">
            <thead><tr><th>{{ __('Product') }}</th><th class="text-end">{{ __('Batches') }}</th><th class="text-end">{{ __('Made') }}</th><th class="text-end">{{ __('Cost') }}</th><th class="text-end">{{ __('Cost of one') }}</th></tr></thead>
            <tbody>
                @php
                    $total = '0';
                @endphp
                @forelse ($products as $row)
                    @php
                        $cost = \App\Support\Num::money($row->cost);
                        $total = \App\Support\Num::add($total, $cost);
                    @endphp
                    <tr>
                        <td>{{ $row->name }}{{ $row->code ? ' ('.$row->code.')' : '' }}</td>
                        <td class="text-end">{{ $row->batches }}</td>
                        <td class="text-end">{{ qty(\App\Support\Num::qty($row->qty)) }}</td>
                        <td class="text-end">{{ money($cost) }}</td>
                        <td class="text-end">{{ money(\App\Support\Num::div($cost, \App\Support\Num::qty($row->qty))) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">{{ __('Nothing was made in these days.') }}</td></tr>
                @endforelse
                <tr class="fw-bold"><td colspan="3">{{ __('Total') }}</td><td class="text-end">{{ money($total, $base) }}</td><td></td></tr>
            </tbody>
        </table>
        <h5 class="mt-4">{{ __('Raw materials used') }}</h5>
        <table class="table table-hover w-100 keep-table">
            <thead><tr><th>{{ __('Raw material or packing') }}</th><th class="text-end">{{ __('Used') }}</th><th class="text-end">{{ __('Cost') }}</th></tr></thead>
            <tbody>
                @forelse ($materials as $row)
                    <tr><td>{{ $row->name }}</td><td class="text-end">{{ qty(\App\Support\Num::qty($row->qty)) }}</td><td class="text-end">{{ money(\App\Support\Num::money($row->cost)) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-3">{{ __('Nothing was used in these days.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
