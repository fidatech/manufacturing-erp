@extends('layouts.app', ['title' => __('Dead Cash')])

@section('content')
<x-page-header :title="__('Dead Cash')" icon="ban" :subtitle="__('Money that will not come back: torn, burnt or false notes, money lost with a sarafi, or a customer\'s debt that will never be paid. It leaves the account and is counted as a loss.')" />

@can('money.manage')
    <form method="POST" action="{{ route('dead-cash.store') }}" class="card mb-4 no-print">
        @csrf
        <div class="card-header"><h5 class="card-title">{{ __('Write off money') }}</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="f-date">{{ __('Date') }} <span class="text-danger">*</span></label>
                    <x-date name="date" :value="old('date', today_iso())" id="f-date" required />
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label" for="f-account">{{ __('Taken from') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="f-account" name="account_id" required data-search>
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('account_id') === (string) $account->id)>{{ $account->name }} ({{ $account->currency->code }}) · {{ __($account->type->name) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="f-amount">{{ __('Amount') }} <span class="text-danger">*</span></label>
                    <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control" id="f-amount" name="amount" value="{{ old('amount') }}" required>
                    <div class="form-text">{{ __('In the currency of that account.') }}</div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="f-reason">{{ __('Reason') }} <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="f-reason" name="reason" value="{{ old('reason') }}" maxlength="500" required placeholder="{{ __('e.g. 3 torn 1,000 afghani notes; the customer left the country') }}">
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end">
            <button type="submit" class="btn btn-danger"><x-icon name="check" class="me-1" /> {{ __('Write it off') }}</button>
        </div>
    </form>
@endcan

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3 no-print">
            @include('partials.date-filter', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null])
            <div class="col-12 col-md-auto">
                <label class="form-label small mb-1">{{ __('Account') }}</label>
                <select class="form-select" name="account">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) ($filters['account'] ?? '') === (string) $account->id)>{{ $account->name }} ({{ $account->currency->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        <div class="alert alert-light border d-flex justify-content-between flex-wrap gap-2 mb-3">
            <span>{{ __('Written off, in :base', ['base' => $base]) }}</span>
            <strong class="fs-5 text-danger">{{ money($total, $base) }}</strong>
        </div>

        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('No.') }}</th><th>{{ __('Details') }}</th><th>{{ __('Accounts') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($entry->date) }}</td>
                        <td class="text-nowrap">{{ $entry->number }}@if ($entry->isVoid()) <span class="badge-soft danger">{{ __('Cancelled') }}</span>@endif</td>
                        <td>{{ $entry->description }}</td>
                        <td>@include('partials.entry-lines')</td>
                        <td class="text-end">
                            @if (! $entry->isVoid())
                                @can('money.manage')
                                    <form method="POST" action="{{ route('dead-cash.destroy', ['id' => $entry->id]) }}" class="d-inline" data-confirm="{{ __('Cancel this entry?') }}" data-confirm-text="{{ __('The money goes back to the account.') }}" data-confirm-ok="{{ __('Cancel it') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light-danger">{{ __('Cancel') }}</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $entries->links('partials.pagination') }}
    </div>
</div>
@endsection
