@extends('layouts.app', ['title' => __('Capital')])

@section('content')
<x-page-header :title="__('Capital')" icon="sack-dollar" :subtitle="__('Money the shareholders put into the company.')" />

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="card stat-card mb-0"><div class="sc-top"><div><div class="sc-label">{{ __('Capital put in') }}</div><div class="sc-value">{{ money($cards['in']) }}</div></div><span class="sc-icon icon-soft success"><x-icon name="arrow-down" /></span></div><div class="sc-foot">{{ $base }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="card stat-card mb-0"><div class="sc-top"><div><div class="sc-label">{{ __('Taken out') }}</div><div class="sc-value">{{ money($cards['out']) }}</div></div><span class="sc-icon icon-soft danger"><x-icon name="arrow-up" /></span></div><div class="sc-foot">{{ $base }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="card stat-card mb-0"><div class="sc-top"><div><div class="sc-label">{{ __('Profit shared') }}</div><div class="sc-value">{{ money($cards['profit']) }}</div></div><span class="sc-icon icon-soft warning"><x-icon name="chart-pie" /></span></div><div class="sc-foot">{{ $base }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="card stat-card mb-0"><div class="sc-top"><div><div class="sc-label">{{ __('Owners\' money in the company now') }}</div><div class="sc-value">{{ money($cards['balance']) }}</div></div><span class="sc-icon icon-soft primary"><x-icon name="handshake" /></span></div><div class="sc-foot">{{ $base }}</div></div></div>
</div>

@can('money.manage')
    <form method="POST" action="{{ route('money.store', ['kind' => 'in']) }}" class="card mb-4" data-money-form>
        @csrf
        <input type="hidden" name="group" value="shareholder">
        <input type="hidden" name="return" value="capital">
        <div class="card-header"><h5 class="card-title">{{ __('Add capital') }}</h5></div>
        <div class="card-body">
            @if ($shareholders->isEmpty())
                <div class="alert alert-info mb-0">{{ __('Add a shareholder first (Shareholders > Shareholder Management).') }}</div>
            @else
                <div class="row g-3">
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="f-date">{{ __('Date') }} <span class="text-danger">*</span></label>
                        <x-date name="date" :value="old('date', today_iso())" id="f-date" required />
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="f-other">{{ __('Shareholder') }} <span class="text-danger">*</span></label>
                        <select class="form-select @error('other_id') is-invalid @enderror" id="f-other" name="other_id" required data-currency-of="other">
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($shareholders as $account)
                                <option value="{{ $account->id }}" data-cur="{{ $account->currency->code }}" @selected((string) old('other_id') === (string) $account->id)>{{ $account->name }} ({{ $account->currency->code }})</option>
                            @endforeach
                        </select>
                        @error('other_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="f-money">{{ __('Put the money in') }} <span class="text-danger">*</span></label>
                        <select class="form-select @error('money_id') is-invalid @enderror" id="f-money" name="money_id" required data-currency-of="money">
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($moneyAccounts as $account)
                                <option value="{{ $account->id }}" data-cur="{{ $account->currency->code }}" @selected((string) old('money_id') === (string) $account->id)>{{ $account->name }} ({{ $account->currency->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="f-amount">{{ __('Amount') }} <span data-cur-label="money" class="text-muted"></span> <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control @error('amount') is-invalid @enderror" id="f-amount" name="amount" value="{{ old('amount') }}" required>
                        @error('amount')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 col-md-4" data-other-amount hidden>
                        <label class="form-label" for="f-other-amount">{{ __('Same amount in') }} <span data-cur-label="other"></span></label>
                        <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control" id="f-other-amount" name="other_amount" value="{{ old('other_amount') }}">
                        <div class="form-text">{{ __('The two accounts use different currencies. Leave empty to use the saved exchange rate.') }}</div>
                    </div>
                    <div class="col-12 col-md">
                        <label class="form-label" for="f-description">{{ __('Note') }}</label>
                        <input type="text" class="form-control" id="f-description" name="description" value="{{ old('description') }}" maxlength="500">
                    </div>
                    <div class="col-12 col-md-auto d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><x-icon name="check" class="me-1" /> {{ __('Save capital') }}</button>
                    </div>
                </div>
            @endif
        </div>
    </form>
@endcan

<div class="card mb-4">
    <div class="card-header"><h5 class="card-title">{{ __('Capital received') }}</h5></div>
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('No.') }}</th><th>{{ __('Shareholder') }}</th><th>{{ __('Put in') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Note') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($entries as $entry)
                    @php
                        $owner = $entry->lines->first(fn ($line) => $line->account->type->key === 'shareholder');
                        $money = $entry->lines->first(fn ($line) => $line->account->type->is_money);
                    @endphp
                    <tr class="{{ $entry->isVoid() ? 'text-decoration-line-through text-muted' : '' }}">
                        <td class="text-nowrap">{{ fmt_date($entry->date) }}</td>
                        <td class="text-nowrap">{{ $entry->number }}</td>
                        <td>{{ $owner?->account->name }}</td>
                        <td>{{ $money?->account->name }}</td>
                        <td class="text-end text-nowrap fw-semibold">{{ $money ? money($money->debit, $money->account->currency->code) : '' }}</td>
                        <td>{{ $entry->description }}</td>
                        <td class="text-end">
                            @if (! $entry->isVoid() && ! $entry->source_type)
                                @can('money.manage')
                                    <form method="POST" action="{{ route('money.destroy', ['id' => $entry->id]) }}" class="d-inline" data-confirm="{{ __('Cancel this entry?') }}" data-confirm-ok="{{ __('Cancel it') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light-danger">{{ __('Cancel') }}</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $entries->links('partials.pagination') }}
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title">{{ __('Shareholders') }}</h5></div>
    <div class="card-body">
        <table class="table w-100 keep-table mb-0">
            <tbody>
                @foreach ($shareholders as $account)
                    <tr>
                        <td><a href="{{ route('statement', ['id' => $account->id]) }}">{{ $account->name }}</a></td>
                        <td class="text-end">{{ qty($account->metaValue('share_percent', 0)) }}%</td>
                        <td class="text-end text-nowrap">{{ $account->describeBalance($balances[$account->id]['own'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
