@extends('layouts.app', ['title' => $exchange ? __('Change exchange').' '.$exchange->number : __('New exchange')])

@php
    use App\Support\Num;
@endphp

@section('content')
<x-page-header :title="$exchange ? __('Change exchange').' '.$exchange->number : __('New exchange')" icon="money-bill-transfer" :crumbs="[[__('Currency Exchange'), route('exchange.index')], [$exchange ? $exchange->number : __('New exchange')]]" />

<form method="POST" action="{{ $exchange ? route('exchange.update', ['id' => $exchange->id]) : route('exchange.store') }}" class="card mb-4" data-exchange data-rates="{{ json_encode($rates) }}" data-rates-url="{{ route('exchange.rates') }}"
      data-text-table="{{ __('Rate from the Exchange Rates table') }}" data-text-manual="{{ __('Your own rate') }}" data-text-none="{{ __('No rate is saved for these currencies. Type the rate.') }}">
    @csrf
    @if ($exchange)
        @method('PUT')
    @endif
    <div class="card-body">
        @if ($holders->isEmpty())
            <div class="alert alert-info mb-0">{{ __('To exchange, an account needs two currencies. Open a cash box, bank, sarafi or customer and add a second currency to it.') }}</div>
        @else
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <label class="form-label" for="f-date">{{ __('Date') }} <span class="text-danger">*</span></label>
                    <x-date name="date" :value="old('date', $exchange?->date?->toDateString() ?? today_iso())" id="f-date" required />
                </div>
                <div class="col-12 col-md-9">
                    <label class="form-label" for="f-holder">{{ __('Account') }} <span class="text-danger">*</span></label>
                    <select class="form-select @error('holder') is-invalid @enderror" id="f-holder" name="holder" required data-search>
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($holders as $holder)
                            <option value="{{ $holder['key'] }}" data-currencies="{{ implode(',', array_keys($holder['accounts'])) }}" @selected(old('holder', $holderKey) === $holder['key'])>{{ $holder['name'] }} · {{ __($holder['kind']) }} ({{ implode(', ', array_map(fn ($account) => $account->currency->code, $holder['accounts'])) }})</option>
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
                            <option value="{{ $currency->id }}" data-code="{{ $currency->code }}" @selected((string) old('sell_currency_id', $exchange?->sellAccount->currency_id) === (string) $currency->id)>{{ $currency->code }} · {{ $currency->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="f-sell-amount">{{ __('Selling amount') }} <span class="text-muted" data-sell-code></span> <span class="text-danger">*</span></label>
                    <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control @error('sell_amount') is-invalid @enderror" id="f-sell-amount" name="sell_amount" value="{{ old('sell_amount', $exchange ? money($exchange->sell_amount) : '') }}" required>
                    @error('sell_amount')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="f-rate">{{ __('Exchange rate') }} <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control @error('rate') is-invalid @enderror" id="f-rate" name="rate" value="{{ old('rate', $exchange ? Num::format($exchange->rate, 6, true) : '') }}">
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
                        <option value="multiply" @selected(old('operation', $exchange?->operation) === 'multiply')>× {{ __('Multiply') }}</option>
                        <option value="divide" @selected(old('operation', $exchange?->operation) === 'divide')>÷ {{ __('Divide') }}</option>
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label" for="f-buy-currency">{{ __('Buyer currency') }} <span class="text-danger">*</span></label>
                    <select class="form-select @error('buy_currency_id') is-invalid @enderror" id="f-buy-currency" name="buy_currency_id" required data-exchange-buy>
                        @foreach ($currencies as $currency)
                            <option value="{{ $currency->id }}" data-code="{{ $currency->code }}" @selected((string) old('buy_currency_id', $exchange?->buyAccount->currency_id) === (string) $currency->id)>{{ $currency->code }} · {{ $currency->name }}</option>
                        @endforeach
                    </select>
                    @error('buy_currency_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="f-buy-amount">{{ __('Buying amount') }} <span class="text-muted" data-buy-code></span></label>
                    <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control @error('buy_amount') is-invalid @enderror" id="f-buy-amount" name="buy_amount" value="{{ old('buy_amount', $exchange ? money($exchange->buy_amount) : '') }}">
                    @error('buy_amount')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="f-description">{{ __('Description') }}</label>
                    <input type="text" class="form-control" id="f-description" name="description" value="{{ old('description', $exchange?->description) }}" maxlength="500">
                </div>

                <div class="col-12">
                    <div class="exchange-sum" data-exchange-sum hidden></div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save exchange') }}</button>
                    <a class="btn btn-light-secondary ms-1" href="{{ route('exchange.index') }}">{{ __('Back to the list') }}</a>
                </div>
            </div>
        @endif
    </div>
</form>
@endsection
