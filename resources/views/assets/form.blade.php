@extends('layouts.app', ['title' => __('Add :name', ['name' => __('Asset')])])

@section('content')
<x-page-header :title="__('Add :name', ['name' => __('Asset')])" icon="industry" :crumbs="[[__('Assets'), route('assets.index')], [__('Add')]]" />

<form method="POST" action="{{ route('assets.store') }}" class="card">
    @csrf
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="f-name">{{ __('Name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="f-name" name="name" value="{{ old('name') }}" required placeholder="{{ __('Filling machine, office furniture, licence…') }}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="f-class">{{ __('How long is it kept?') }}</label>
                <select class="form-select" id="f-class" name="class">
                    <option value="fixed" @selected(old('class', 'fixed') === 'fixed')>{{ __('Fixed (kept for years)') }}</option>
                    <option value="current" @selected(old('class') === 'current')>{{ __('Current (used within a year)') }}</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="f-kind">{{ __('What is it?') }}</label>
                <select class="form-select" id="f-kind" name="kind">
                    <option value="tangible" @selected(old('kind', 'tangible') === 'tangible')>{{ __('Tangible (can be touched)') }}</option>
                    <option value="intangible" @selected(old('kind') === 'intangible')>{{ __('Intangible (licence, software)') }}</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="f-date">{{ __('Bought on') }} <span class="text-danger">*</span></label>
                <x-date name="purchase_date" :value="old('purchase_date', today_iso())" id="f-date" required />
                @error('purchase_date')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="f-quantity">{{ __('How many') }}</label>
                <input type="text" inputmode="decimal" dir="ltr" class="form-control" id="f-quantity" name="quantity" value="{{ old('quantity', 1) }}">
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label" for="f-cost">{{ __('Total cost') }} <span class="text-danger">*</span></label>
                <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control @error('cost') is-invalid @enderror" id="f-cost" name="cost" value="{{ old('cost') }}" required>
                @error('cost')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="f-currency">{{ __('Currency') }}</label>
                <select class="form-select" id="f-currency" name="currency_id">
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->id }}" @selected((string) old('currency_id') === (string) $currency->id)>{{ $currency->code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="f-paid">{{ __('Paid from') }}</label>
                <select class="form-select" id="f-paid" name="paid_from_id" data-search>
                    <option value="">{{ __('Not paid now: we already had it') }}</option>
                    @foreach ($payers as $account)
                        <option value="{{ $account->id }}" @selected((string) old('paid_from_id') === (string) $account->id)>{{ $account->name }} ({{ $account->currency->code }}) · {{ __($account->type->name) }}</option>
                    @endforeach
                </select>
                <div class="form-text">{{ __('Choose a supplier when it was bought on credit.') }}</div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="f-life">{{ __('Life in months') }}</label>
                <input type="number" min="0" max="1200" class="form-control" id="f-life" name="life_months" value="{{ old('life_months', 0) }}">
                <div class="form-text">{{ __('For example 60 for five years. 0 = it does not lose value.') }}</div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="f-salvage">{{ __('Worth at the end (:base)', ['base' => $base]) }}</label>
                <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control" id="f-salvage" name="salvage_base" value="{{ old('salvage_base', 0) }}">
            </div>
            <div class="col-12">
                <label class="form-label" for="f-notes">{{ __('Notes') }}</label>
                <textarea class="form-control" id="f-notes" name="notes" rows="2">{{ old('notes') }}</textarea>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('assets.index') }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>
@endsection
