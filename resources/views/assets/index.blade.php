@extends('layouts.app', ['title' => __('Assets')])

@section('content')
<x-page-header :title="__('Assets')" icon="industry" :subtitle="__('Machines, furniture, licences and other things the company keeps.')">
    @can('assets.manage')
        <a class="btn btn-primary" href="{{ route('assets.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Add :name', ['name' => __('Asset')]) }}</a>
    @endcan
</x-page-header>

@can('assets.manage')
    <div class="card mb-4">
        <div class="card-header"><div><h5 class="card-title">{{ __('Monthly loss of value (depreciation and amortization)') }}</h5><div class="small text-muted">{{ __('Press once a month. Every asset with a life loses one month of its value. Pressing twice does no harm.') }}</div></div></div>
        <div class="card-body">
            <form method="POST" action="{{ route('assets.depreciate') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-8 col-md-auto">
                    <label class="form-label small mb-1" for="f-period">{{ __('Month') }}</label>
                    <input type="month" class="form-control" id="f-period" name="period" value="{{ now()->format('Y-m') }}" required>
                </div>
                <div class="col-4 col-md-auto"><button type="submit" class="btn btn-primary w-100">{{ __('Write off this month') }}</button></div>
            </form>
            @if ($periods->isNotEmpty())
                <table class="table w-100 mt-3 keep-table">
                    <thead><tr><th>{{ __('Month') }}</th><th>{{ __('Assets') }}</th><th class="text-end">{{ __('Written off (:base)', ['base' => $base]) }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($periods as $period)
                            <tr>
                                <td>{{ $period->period }}</td><td>{{ $period->assets }}</td><td class="text-end">{{ money($period->total) }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('assets.undo') }}" class="d-inline" data-confirm="{{ __('Take back the write-off of :period?', ['period' => $period->period]) }}" data-confirm-ok="{{ __('Take it back') }}">
                                        @csrf
                                        <input type="hidden" name="period" value="{{ $period->period }}">
                                        <button type="submit" class="btn btn-sm btn-light-danger">{{ __('Take back') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endcan

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-6 col-md-auto">
                <select class="form-select" name="class" aria-label="{{ __('Fixed or current') }}">
                    <option value="">{{ __('Fixed and current') }}</option>
                    <option value="fixed" @selected(request('class') === 'fixed')>{{ __('Fixed (kept for years)') }}</option>
                    <option value="current" @selected(request('class') === 'current')>{{ __('Current (used within a year)') }}</option>
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <select class="form-select" name="kind" aria-label="{{ __('Tangible or intangible') }}">
                    <option value="">{{ __('Tangible and intangible') }}</option>
                    <option value="tangible" @selected(request('kind') === 'tangible')>{{ __('Tangible (can be touched)') }}</option>
                    <option value="intangible" @selected(request('kind') === 'intangible')>{{ __('Intangible (licence, software)') }}</option>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Asset') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Bought on') }}</th><th class="text-end">{{ __('Cost') }}</th><th class="text-end">{{ __('Written off') }}</th><th class="text-end">{{ __('Worth now (:base)', ['base' => $base]) }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $asset = $row['asset'];
                    @endphp
                    <tr>
                        <td>{{ $asset->name }}@if ($asset->status !== 'active') <span class="badge-soft danger">{{ __('Removed') }}</span>@endif<div class="small text-muted">{{ $asset->life_months ? __('Life: :months months', ['months' => $asset->life_months]) : __('Does not lose value') }}</div></td>
                        <td>{{ $asset->class === 'fixed' ? __('Fixed') : __('Current') }} · {{ $asset->kind === 'tangible' ? __('Tangible') : __('Intangible') }}</td>
                        <td class="text-nowrap">{{ fmt_date($asset->purchase_date) }}</td>
                        <td class="text-end text-nowrap">{{ money($asset->cost, $asset->currency->code) }}</td>
                        <td class="text-end text-nowrap">{{ money($row['off']) }}</td>
                        <td class="text-end text-nowrap fw-semibold">{{ money($row['value']) }}</td>
                        <td class="text-end">
                            @if ($asset->status === 'active')
                                @can('assets.manage')
                                    <button type="button" class="btn btn-sm btn-light-danger" data-bs-toggle="modal" data-bs-target="#dispose-{{ $asset->id }}">{{ __('Remove') }}</button>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
                <tr class="fw-bold"><td colspan="3">{{ __('Total') }} ({{ $base }})</td><td class="text-end">{{ money($totals['cost']) }}</td><td class="text-end">{{ money($totals['off']) }}</td><td class="text-end">{{ money($totals['value']) }}</td><td></td></tr>
            </tbody>
        </table>
    </div>
</div>

@can('assets.manage')
    @foreach ($rows as $row)
        @if ($row['asset']->status === 'active')
            <div class="modal fade" id="dispose-{{ $row['asset']->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" action="{{ route('assets.dispose', ['id' => $row['asset']->id]) }}" class="modal-content">
                        @csrf
                        <div class="modal-header"><h5 class="modal-title">{{ __('Remove :name', ['name' => $row['asset']->name]) }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
                        <div class="modal-body">
                            <p class="text-muted">{{ __('Use this when the asset was sold, broke or was given away.') }}</p>
                            <div class="mb-3"><label class="form-label">{{ __('Date') }}</label><x-date name="date" :value="today_iso()" required /></div>
                            <div class="mb-3"><label class="form-label">{{ __('Sold for (leave empty if not sold)') }}</label><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control" name="sold_for"></div>
                            <div><label class="form-label">{{ __('Put the money in') }}</label>
                                <select class="form-select" name="money_id">
                                    <option value="">{{ __('Choose…') }}</option>
                                    @foreach ($moneyAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->currency->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-danger">{{ __('Remove') }}</button></div>
                    </form>
                </div>
            </div>
        @endif
    @endforeach
@endcan
@endsection
