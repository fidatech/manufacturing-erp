@extends('layouts.app', ['title' => __('Petty Cash')])

@section('content')
<x-page-header :title="__('Petty Cash')" icon="vault" :subtitle="__('The cash boxes of the company and the small expenses paid from them.')">
    @can('money.manage')
        <a class="btn btn-warning" href="{{ route('money.create', ['kind' => 'expense']) }}"><x-icon name="receipt" class="me-1" /> {{ __('Expense') }}</a>
        <a class="btn btn-outline-soft" href="{{ route('money.create', ['kind' => 'transfer', 'group' => 'transfer']) }}"><x-icon name="arrow-right-arrow-left" class="me-1" /> {{ __('Fill a cash box') }}</a>
    @endcan
    @can('accounts.manage')
        <a class="btn btn-outline-soft" href="{{ route('accounts.create', ['type' => 'cash']) }}"><x-icon name="plus" class="me-1" /> {{ __('Cash Box') }}</a>
    @endcan
</x-page-header>

<div class="row g-3 mb-4">
    @foreach ($boxes as $box)
        <div class="col-6 col-xl-3">
            <div class="card stat-card mb-0">
                <div class="sc-top"><div><div class="sc-label">{{ $box->name }}</div><div class="sc-value">{{ money($balances[$box->id]['own'] ?? 0) }}</div></div><span class="sc-icon icon-soft primary"><x-icon name="vault" /></span></div>
                <div class="sc-foot">{{ $box->currency->code }} · <a href="{{ route('statement', ['id' => $box->id]) }}">{{ __('Statement') }}</a></div>
            </div>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title">{{ __('Latest cash movements') }}</h5></div>
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('No.') }}</th><th>{{ __('Details') }}</th><th>{{ __('Accounts') }}</th></tr></thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($entry->date) }}</td>
                        <td class="text-nowrap">{{ $entry->number }}</td>
                        <td>{{ $entry->description }}</td>
                        <td>@include('partials.entry-lines')</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
