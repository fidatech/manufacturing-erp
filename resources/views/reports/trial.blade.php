@extends('layouts.app', ['title' => __('Trial balance')])

@section('content')
<x-page-header :title="__('Trial balance')" icon="list-check" :crumbs="[[__('Reports'), route('reports.index')], [__('Trial balance')]]" />

<div class="card">
    <div class="card-body">
        @include('reports.filter', ['range' => false])
        <table class="table table-hover w-100 keep-table">
            <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Kind') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th></tr></thead>
            <tbody>
                @foreach ($rows as $account)
                    <tr>
                        <td><a href="{{ route('statement', ['id' => $account->id]) }}">{{ $account->name }}</a></td>
                        <td>{{ __($account->type->name) }}</td>
                        <td class="text-end">{{ \App\Support\Num::isPositive($account->base) ? money($account->base) : '' }}</td>
                        <td class="text-end">{{ \App\Support\Num::isNegative($account->base) ? money(\App\Support\Num::abs($account->base)) : '' }}</td>
                    </tr>
                @endforeach
                <tr class="fw-bold"><td colspan="2">{{ __('Total') }}</td><td class="text-end">{{ money($debit) }}</td><td class="text-end">{{ money($credit) }}</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
