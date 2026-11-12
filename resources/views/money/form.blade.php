@extends('layouts.app', ['title' => $heading])

@php
    $labels = [
        'in' => [__('Received from'), __('Put the money in')],
        'out' => [__('Paid to'), __('Take the money from')],
        'expense' => [__('Spent on'), __('Take the money from')],
        'transfer' => [__('Move the money to'), __('Take the money from')],
    ][$kind];
@endphp

@section('content')
<x-page-header :title="$heading" icon="money-bill-wave" :crumbs="[[__('Money'), route('money.index')], [$heading]]" />

<form method="POST" action="{{ route('money.store', ['kind' => $kind]) }}" class="card" data-money-form @if (in_array($kind, ['in', 'out'], true)) data-auto-money @endif>
    @csrf
    <input type="hidden" name="group" value="{{ $group }}">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="f-date">{{ __('Date') }} <span class="text-danger">*</span></label>
                <x-date name="date" :value="old('date', today_iso())" id="f-date" required />
                @error('date')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12 col-md-8">
                <label class="form-label" for="f-other">{{ $labels[0] }} <span class="text-danger">*</span></label>
                <select class="form-select @error('other_id') is-invalid @enderror" id="f-other" name="other_id" {{ $quickOther ?? '' }} required data-search data-currency-of="other">
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($others as $account)
                        <option value="{{ $account->id }}" data-cur="{{ $account->currency->code }}" @selected((string) old('other_id', request('account')) === (string) $account->id)>{{ $account->name }} ({{ $account->currency->code }}) · {{ __($account->type->name) }}</option>
                    @endforeach
                </select>
                @error('other_id')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="f-money">{{ $labels[1] }} <span class="text-danger">*</span></label>
                <select class="form-select @error('money_id') is-invalid @enderror" id="f-money" name="money_id" required data-currency-of="money">
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($moneyAccounts as $account)
                        <option value="{{ $account->id }}" data-cur="{{ $account->currency->code }}" @if ($account->type->key === 'cash') data-cash @endif @selected((string) old('money_id') === (string) $account->id)>{{ $account->name }} ({{ $account->currency->code }})</option>
                    @endforeach
                </select>
                @error('money_id')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="f-amount">{{ __('Amount') }} <span data-cur-label="money" class="text-muted"></span> <span class="text-danger">*</span></label>
                <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control @error('amount') is-invalid @enderror" id="f-amount" name="amount" value="{{ old('amount') }}" required>
                @error('amount')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12 col-md-4" data-other-amount hidden>
                <label class="form-label" for="f-other-amount">{{ __('Same amount in') }} <span data-cur-label="other"></span></label>
                <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control @error('other_amount') is-invalid @enderror" id="f-other-amount" name="other_amount" value="{{ old('other_amount') }}">
                <div class="form-text">{{ __('The two accounts use different currencies. Leave empty to use the saved exchange rate.') }}</div>
                @error('other_amount')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="f-description">{{ __('Note') }}</label>
                <input type="text" class="form-control" id="f-description" name="description" value="{{ old('description') }}" maxlength="500">
            </div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('money.index') }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>
@endsection
