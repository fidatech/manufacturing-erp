@php
    $heading = $grn ? __('Goods received notes') : $names[$type];
@endphp
@extends('layouts.app', ['title' => $heading])

@section('content')
<x-page-header :title="$heading" icon="cart-flatbed" :subtitle="$grn ? __('Open a purchase that is on the way and press Goods received when it arrives.') : null">
    @can('purchases.manage')
        <a class="btn btn-primary" href="{{ route('purchases.create', ['type' => $type]) }}"><x-icon name="plus" class="me-1" /> {{ __('Add :name', ['name' => \App\Http\Controllers\Trade\PurchaseController::singular($type)]) }}</a>
    @endcan
</x-page-header>

@unless ($grn)
    @include('partials.type-tabs', ['routeName' => 'purchases.index'])
@endunless

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="type" value="{{ $type }}">
            @if ($grn)
                <input type="hidden" name="view" value="grn">
            @endif
            @include('partials.date-filter', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null])
            <div class="col">
                <label class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Number or supplier') }}">
            </div>
            @if ($type === 'invoice')
                <div class="col-auto">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="f-waiting" name="waiting" value="1" @checked(request()->boolean('waiting'))>
                        <label class="form-check-label" for="f-waiting">{{ __('Goods on the way only') }}</label>
                    </div>
                </div>
            @endif
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Supplier') }}</th>@if ($grn)<th>{{ __('GRN') }}</th><th>{{ __('Received on') }}</th>@else<th class="text-end">{{ __('Total') }}</th>@endif<th>{{ __('Status') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($purchases as $purchase)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('purchases.show', ['id' => $purchase->id]) }}" class="fw-semibold">{{ $purchase->number }}</a></td>
                        <td class="text-nowrap">{{ fmt_date($purchase->date) }}</td>
                        <td>{{ $purchase->supplier->name }}</td>
                        @if ($grn)
                            <td>{{ $purchase->grn_number }}</td>
                            <td>{{ fmt_date($purchase->received_date) }}</td>
                        @else
                            <td class="text-end text-nowrap">{{ money($purchase->total, $purchase->currency->code) }}</td>
                        @endif
                        <td>@include('purchases.status')</td>
                        <td class="text-end"><a class="btn btn-sm btn-light-secondary" href="{{ route('purchases.show', ['id' => $purchase->id]) }}">{{ __('Open') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $purchases->links('partials.pagination') }}
    </div>
</div>
@endsection
