@extends('layouts.app', ['title' => __('Currency Exchange')])

@php
    use App\Support\Num;

    $signs = ['multiply' => '×', 'divide' => '÷'];
@endphp

@section('content')
<x-page-header :title="__('Currency Exchange')" icon="money-bill-transfer" :subtitle="__('Sell one currency and buy another, at the saved rate or at your own rate.')">
    @can('money.manage')
        <a class="btn btn-primary" href="{{ route('exchange.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Add new exchange') }}</a>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3 no-print">
            @include('partials.date-filter', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null])
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>
        <table class="table table-hover w-100">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th><th>{{ __('No.') }}</th><th>{{ __('Account') }}</th>
                    <th class="text-end">{{ __('Sold') }}</th><th class="text-end">{{ __('Rate') }}</th><th class="text-end">{{ __('Bought') }}</th>
                    <th>{{ __('Description') }}</th><th class="text-end no-print">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($exchanges as $exchange)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($exchange->date) }}</td>
                        <td class="text-nowrap">{{ $exchange->number }}</td>
                        <td>
                            @can('accounts.view')
                                <a href="{{ route('statement', ['id' => $exchange->sell_account_id]) }}">{{ $exchange->sellAccount->name }}</a> → <a href="{{ route('statement', ['id' => $exchange->buy_account_id]) }}">{{ $exchange->buyAccount->name }}</a>
                            @else
                                {{ $exchange->sellAccount->name }} → {{ $exchange->buyAccount->name }}
                            @endcan
                        </td>
                        <td class="text-end text-nowrap fw-semibold">{{ money($exchange->sell_amount, $exchange->sellAccount->currency->code) }}</td>
                        <td class="text-end text-nowrap">{{ $signs[$exchange->operation] ?? '' }} {{ Num::format($exchange->rate, 6, true) }}</td>
                        <td class="text-end text-nowrap fw-semibold">{{ money($exchange->buy_amount, $exchange->buyAccount->currency->code) }}</td>
                        <td>{{ $exchange->description }}</td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-light-secondary" href="{{ route('exchange.print', ['id' => $exchange->id]) }}" target="_blank" rel="noopener" title="{{ __('Print receipt') }}"><x-icon name="print" /> <span class="d-none d-xl-inline">{{ __('Receipt') }}</span></a>
                            @can('money.manage')
                                <a class="btn btn-sm btn-light-secondary" href="{{ route('exchange.edit', ['id' => $exchange->id]) }}" title="{{ __('Edit') }}"><x-icon name="pen" /> <span class="d-none d-xl-inline">{{ __('Edit') }}</span></a>
                                <form method="POST" action="{{ route('exchange.destroy', ['id' => $exchange->id]) }}" class="d-inline" data-confirm="{{ __('Delete this exchange? The money goes back to where it was.') }}" data-confirm-ok="{{ __('Delete') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light-danger" title="{{ __('Delete') }}"><x-icon name="trash" /> <span class="d-none d-xl-inline">{{ __('Delete') }}</span></button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $exchanges->links('partials.pagination') }}
    </div>
</div>
@endsection
