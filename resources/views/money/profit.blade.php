@extends('layouts.app', ['title' => __('Share profit')])

@section('content')
<x-page-header :title="__('Share profit')" icon="chart-pie" :crumbs="[[__('Money'), route('money.index')], [__('Share profit')]]" :subtitle="__('The amount is added to each shareholder\'s account by their share. They take it out later with Money out.')" />

<form method="POST" action="{{ route('money.profit.store') }}" class="card">
    @csrf
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="f-date">{{ __('Date') }} <span class="text-danger">*</span></label>
                <x-date name="date" :value="old('date', today_iso())" id="f-date" required />
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="f-amount">{{ __('Profit to share (:base)', ['base' => $base]) }} <span class="text-danger">*</span></label>
                <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control @error('amount') is-invalid @enderror" id="f-amount" name="amount" value="{{ old('amount') }}" required>
                @error('amount')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="f-description">{{ __('Note') }}</label>
                <input type="text" class="form-control" id="f-description" name="description" value="{{ old('description') }}" maxlength="500">
            </div>
        </div>
        <table class="table w-100 mt-3 keep-table">
            <thead><tr><th>{{ __('Shareholder') }}</th><th class="text-end">{{ __('Share') }}</th></tr></thead>
            <tbody>
                @forelse ($shareholders as $shareholder)
                    <tr><td>{{ $shareholder->name }} ({{ $shareholder->currency->code }})</td><td class="text-end">{{ qty($shareholder->metaValue('share_percent', 0)) }}%</td></tr>
                @empty
                    <tr><td colspan="2" class="text-center text-muted py-3">{{ __('Add shareholders first.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('money.index') }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>

<div class="card mt-4">
    <div class="card-header"><div><h5 class="card-title">{{ __('Monthly profit') }}</h5><div class="small text-muted">{{ __('On the 1st of every month the profit of the month before is added to the shareholders\' accounts by itself. A month is never shared twice.') }}</div></div></div>
    <div class="card-body">
        <table class="table w-100 keep-table">
            <thead><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Net profit (:base)', ['base' => $base]) }}</th><th>{{ __('Shared') }}</th><th class="text-end no-print"></th></tr></thead>
            <tbody>
                @foreach ($months as $row)
                    <tr>
                        <td dir="ltr" class="text-start">{{ $row['month'] }}</td>
                        <td class="text-end {{ \App\Support\Num::isNegative($row['net']) ? 'text-danger' : '' }}">{{ money($row['net'], $base) }}</td>
                        <td>@if ($row['entry'])<span class="badge-soft success">{{ $row['entry']->number }}</span>@else<span class="text-muted">{{ __('Not yet') }}</span>@endif</td>
                        <td class="text-end">
                            @if (! $row['entry'] && \App\Support\Num::isPositive($row['net']))
                                <form method="POST" action="{{ route('money.profit.month') }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="month" value="{{ $row['month'] }}">
                                    <button type="submit" class="btn btn-sm btn-outline-soft">{{ __('Share now') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
