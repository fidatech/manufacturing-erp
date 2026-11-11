{{-- Two date boxes "from / to" for list and report filters. --}}
<div class="col-6 col-md-auto">
    <label class="form-label small mb-1">{{ __('From') }}</label>
    <x-date name="from" :value="$from ?? null" />
</div>
<div class="col-6 col-md-auto">
    <label class="form-label small mb-1">{{ __('To') }}</label>
    <x-date name="to" :value="$to ?? null" />
</div>
