@extends('layouts.app', ['title' => __('Assets')])

@section('content')
<x-page-header :title="__('Assets')" icon="industry" :subtitle="__('Machines, furniture, licences and other things the company keeps.')">
    <a class="btn btn-outline-soft" href="{{ route('assets.write-off', ['kind' => 'depreciation']) }}">{{ __('Depreciation') }}</a>
    <a class="btn btn-outline-soft" href="{{ route('assets.write-off', ['kind' => 'amortization']) }}">{{ __('Amortization') }}</a>
    @can('assets.manage')
        <a class="btn btn-primary" href="{{ route('assets.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Add :name', ['name' => __('Asset')]) }}</a>
    @endcan
</x-page-header>

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
            <thead><tr><th>{{ __('Asset') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Bought on') }}</th><th class="text-end">{{ __('Cost') }}</th><th class="text-end">{{ __('Written off') }}</th><th class="text-end">{{ __('Worth now (:base)', ['base' => $base]) }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $asset = $row['asset'];
                    @endphp
                    <tr>
                        <td>{{ $asset->name }}@if ($asset->status !== 'active') <span class="badge-soft danger">{{ __('Removed') }}</span>@endif<div class="small text-muted">{{ $asset->life_months ? __('Life: :months months', ['months' => $asset->life_months]) : __('Does not lose value') }}</div></td>
                        <td>{{ $asset->class === 'fixed' ? __('Fixed') : __('Current') }} · {{ $asset->kind === 'tangible' ? __('Tangible') : __('Intangible') }}{{ $asset->category ? ' · '.$asset->category : '' }}</td>
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
