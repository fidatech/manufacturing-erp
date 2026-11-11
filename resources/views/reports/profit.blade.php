@extends('layouts.app', ['title' => __('Profit and loss')])

@section('content')
<x-page-header :title="__('Profit and loss')" icon="chart-line" :crumbs="[[__('Reports'), route('reports.index')], [__('Profit and loss')]]" />

<div class="card">
    <div class="card-body">
        @include('reports.filter')
        <table class="table w-100 keep-table">
            <tbody>
                <tr class="table-light"><th colspan="2">{{ __('What we earned') }}</th></tr>
                @foreach ($income as $row)
                    <tr><td>{{ $row['name'] }}</td><td class="text-end">{{ money($row['amount']) }}</td></tr>
                @endforeach
                <tr class="fw-bold"><td>{{ __('Total earned') }}</td><td class="text-end">{{ money($incomeTotal) }}</td></tr>
                <tr><td>{{ __('Cost of the goods we sold') }}</td><td class="text-end">{{ money($cogs) }}</td></tr>
                <tr class="fw-bold"><td>{{ __('Profit on goods') }}</td><td class="text-end">{{ money($gross) }}</td></tr>
                <tr class="table-light"><th colspan="2">{{ __('Expenses') }}</th></tr>
                @foreach ($expenses as $row)
                    <tr><td>{{ $row['name'] }}</td><td class="text-end">{{ money($row['amount']) }}</td></tr>
                @endforeach
                <tr class="fw-bold"><td>{{ __('Total expenses') }}</td><td class="text-end">{{ money($expenseTotal) }}</td></tr>
                <tr class="fw-bold fs-5"><td>{{ \App\Support\Num::isNegative($net) ? __('Loss') : __('Profit') }}</td><td class="text-end {{ \App\Support\Num::isNegative($net) ? 'text-danger' : 'text-success' }}">{{ money($net, $base) }}</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
