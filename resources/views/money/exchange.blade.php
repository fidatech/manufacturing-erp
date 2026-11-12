@extends('layouts.app', ['title' => __('Currency Exchange')])

@php
    use App\Support\Num;

    $signs = ['multiply' => '×', 'divide' => '÷'];
@endphp

@section('content')
<x-page-header :title="__('Currency Exchange')" icon="money-bill-transfer" :subtitle="__('Sell one currency and buy another, at the saved rate or at your own rate.')" />

@can('money.manage')
    <form method="POST" action="{{ route('exchange.store') }}" class="card mb-4" data-exchange data-rates="{{ json_encode($rates) }}" data-rates-url="{{ route('exchange.rates') }}"
          data-text-table="{{ __('Rate from the Exchange Rates table') }}" data-text-manual="{{ __('Your own rate') }}" data-text-none="{{ __('No rate is saved for these currencies. Type the rate.') }}">
        @csrf
        <div class="card-header"><h5 class="card-title">{{ __('New exchange') }}</h5></div>
        <div class="card-body">
            @if ($holders->isEmpty())
                <div class="alert alert-info mb-0">{{ __('To exchange, an account needs two currencies. Open a cash box, bank, sarafi or customer and add a second currency to it.') }}</div>
            @else
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="f-date">{{ __('Date') }} <span class="text-danger">*</span></label>
                        <x-date name="date" :value="old('date', today_iso())" id="f-date" required />
                    </div>
                    <div class="col-12 col-md-9">
                        <label class="form-label" for="f-holder">{{ __('Account') }} <span class="text-danger">*</span></label>
                        <select class="form-select @error('holder') is-invalid @enderror" id="f-holder" name="holder" required data-search>
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($holders as $holder)
                                <option value="{{ $holder['key'] }}" data-currencies="{{ implode(',', array_keys($holder['accounts'])) }}" @selected(old('holder') === $holder['key'])>{{ $holder['name'] }} · {{ __($holder['kind']) }} ({{ implode(', ', array_map(fn ($account) => $account->currency->code, $holder['accounts'])) }})</option>
                            @endforeach
                        </select>
                        @error('holder')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label" for="f-sell-currency">{{ __('Seller currency') }} <span class="text-danger">*</span></label>
                        <select class="form-select @error('sell_currency_id') is-invalid @enderror" id="f-sell-currency" name="sell_currency_id" required data-exchange-sell>
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency->id }}" data-code="{{ $currency->code }}" @selected((string) old('sell_currency_id') === (string) $currency->id)>{{ $currency->code }} · {{ $currency->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="f-sell-amount">{{ __('Selling amount') }} <span class="text-muted" data-sell-code></span> <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control @error('sell_amount') is-invalid @enderror" id="f-sell-amount" name="sell_amount" value="{{ old('sell_amount') }}" required>
                        @error('sell_amount')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="f-rate">{{ __('Exchange rate') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control @error('rate') is-invalid @enderror" id="f-rate" name="rate" value="{{ old('rate') }}">
                            <button type="button" class="btn btn-outline-soft" data-table-rate title="{{ __('Use the saved rate') }}"><x-icon name="rotate" /></button>
                        </div>
                        <div class="form-text" data-rate-hint></div>
                        @error('rate')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="f-operation">{{ __('Operating type') }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="f-operation" name="operation" required>
                            <option value="multiply" @selected(old('operation') === 'multiply')>× {{ __('Multiply') }}</option>
                            <option value="divide" @selected(old('operation') === 'divide')>÷ {{ __('Divide') }}</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label" for="f-buy-currency">{{ __('Buyer currency') }} <span class="text-danger">*</span></label>
                        <select class="form-select @error('buy_currency_id') is-invalid @enderror" id="f-buy-currency" name="buy_currency_id" required data-exchange-buy>
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency->id }}" data-code="{{ $currency->code }}" @selected((string) old('buy_currency_id') === (string) $currency->id)>{{ $currency->code }} · {{ $currency->name }}</option>
                            @endforeach
                        </select>
                        @error('buy_currency_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="f-buy-amount">{{ __('Buying amount') }} <span class="text-muted" data-buy-code></span></label>
                        <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control @error('buy_amount') is-invalid @enderror" id="f-buy-amount" name="buy_amount" value="{{ old('buy_amount') }}">
                        @error('buy_amount')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="f-description">{{ __('Description') }}</label>
                        <input type="text" class="form-control" id="f-description" name="description" value="{{ old('description') }}" maxlength="500">
                    </div>

                    <div class="col-12">
                        <div class="exchange-sum" data-exchange-sum hidden></div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save exchange') }}</button>
                    </div>
                </div>
            @endif
        </div>
    </form>
@endcan

<div class="card">
    <div class="card-header"><h5 class="card-title">{{ __('Exchanges') }}</h5></div>
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
                    <th>{{ __('Description') }}</th><th class="text-end">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($exchanges as $exchange)
                    <tr class="{{ $exchange->isCancelled() ? 'text-decoration-line-through text-muted' : '' }}">
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
                        <td class="text-end">
                            @unless ($exchange->isCancelled())
                                @can('money.manage')
                                    <form method="POST" action="{{ route('exchange.destroy', ['id' => $exchange->id]) }}" class="d-inline" data-confirm="{{ __('Cancel this exchange?') }}" data-confirm-ok="{{ __('Cancel it') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light-danger">{{ __('Cancel') }}</button>
                                    </form>
                                @endcan
                            @endunless
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
