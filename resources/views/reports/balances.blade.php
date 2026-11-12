@extends('layouts.app', ['title' => __('Who owes us, whom we owe')])

@section('content')
<x-page-header :title="__('Who owes us, whom we owe')" icon="scale-balanced" :crumbs="[[__('Reports'), route('reports.index')], [__('Balances')]]" />

<div class="card">
    <div class="card-body">
        @include('reports.filter', ['range' => false, 'heading' => __('Who owes us, whom we owe')])
        @foreach ([[__('They owe us'), $owesUs, 'text-success'], [__('We owe them'), $weOwe, 'text-danger']] as [$heading, $list, $colour])
            <h5 class="mt-3">{{ $heading }}</h5>
            <table class="table table-hover w-100 keep-table">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Phone') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('In :base', ['base' => $base]) }}</th></tr></thead>
                <tbody>
                    @php
                        $sum = '0';
                    @endphp
                    @forelse ($list as $account)
                        @php
                            $sum = \App\Support\Num::add($sum, \App\Support\Num::abs($account->base));
                        @endphp
                        <tr>
                            <td><a href="{{ route('statement', ['id' => $account->id]) }}">{{ $account->name }}</a></td>
                            <td>{{ __($account->type->name) }}</td>
                            <td>{{ $account->phone }}</td>
                            <td class="text-end text-nowrap {{ $colour }}">{{ money(\App\Support\Num::abs($account->own), $account->currency->code) }}</td>
                            <td class="text-end text-nowrap">{{ money(\App\Support\Num::abs($account->base)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">{{ __('Nobody') }}</td></tr>
                    @endforelse
                    <tr class="fw-bold"><td colspan="4">{{ __('Total') }}</td><td class="text-end">{{ money($sum, $base) }}</td></tr>
                </tbody>
            </table>
        @endforeach
    </div>
</div>
@endsection
