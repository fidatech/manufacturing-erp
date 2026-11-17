{{-- Currency of a paper. Choosing it keeps only the customers or suppliers with an account in that currency. --}}
<label class="form-label" for="f-currency">{{ __('Currency') }}</label>
<select class="form-select" id="f-currency" name="currency_id" data-currency-pick>
    <option value="">{{ __('All currencies') }}</option>
    @foreach ($currencies as $currency)
        <option value="{{ $currency->id }}" data-code="{{ $currency->code }}" @selected((string) ($selected ?? '') === (string) $currency->id)>{{ $currency->code }} · {{ __($currency->name) }}</option>
    @endforeach
</select>
