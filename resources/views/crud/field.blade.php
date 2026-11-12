@php
    $name = $field['name'];
    $type = $field['type'] ?? 'text';
    $stored = ! empty($field['meta']) ? ($record?->meta[$name] ?? null) : ($record?->{$name} ?? null);
    if ($type === 'password' || ! empty($field['virtual'])) {
        $stored = null;
    }
    $fallback = array_key_exists('value', $field) ? $field['value'] : ($record ? null : ($field['default'] ?? null));
    $value = old($name, $stored ?? $fallback);
    $required = in_array('required', $field['rules'] ?? [], true);
    $readonly = ! empty($field['readonly']);
@endphp
@if (! empty($field['hidden']))
    @php
        return;
    @endphp
@endif
<div class="col-12 col-md-{{ $field['col'] ?? 6 }}">
    @if ($type === 'currencies')
        <label class="form-label">{{ __($field['label']) }} <span class="text-danger">*</span></label>
        <div class="row g-2">
            @foreach ($field['options'] as $currencyId => $code)
                @php
                    $ticked = old('currencies') !== null ? ! empty(old("currencies.$currencyId.on")) : ($currencyId === ($field['default'] ?? null));
                @endphp
                <div class="col-12 {{ empty($field['sides']) ? 'col-sm-4 col-lg-3' : 'col-xl-6' }}">
                    <div class="input-group">
                        <label class="input-group-text gap-2 mb-0" for="f-cur-{{ $currencyId }}">
                            <input class="form-check-input mt-0" type="checkbox" id="f-cur-{{ $currencyId }}" name="currencies[{{ $currencyId }}][on]" value="1" @checked($ticked)>
                            <strong>{{ $code }}</strong>
                        </label>
                        @if (! empty($field['sides']))
                            <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="currencies[{{ $currencyId }}][opening]" value="{{ old("currencies.$currencyId.opening") }}" placeholder="{{ __('Opening balance') }}" aria-label="{{ __('Opening balance') }} {{ $code }}">
                            <select class="form-select erp-side" name="currencies[{{ $currencyId }}][side]" aria-label="{{ __('Opening balance is') }} {{ $code }}">
                                @foreach ($field['sides'] as $side => $sideLabel)
                                    <option value="{{ $side }}" @selected(old("currencies.$currencyId.side", $field['side'] ?? 'debit') === $side)>{{ $sideLabel }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @error('currencies')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @if (! empty($field['help']))
            <div class="form-text">{{ __($field['help']) }}</div>
        @endif
    @elseif ($type === 'checkbox')
        <div class="form-check form-switch mt-md-4 pt-md-2">
            <input type="hidden" name="{{ $name }}" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="f-{{ $name }}" name="{{ $name }}" value="1" @checked(old($name, $stored ?? ($field['default'] ?? false)))>
            <label class="form-check-label" for="f-{{ $name }}">{{ __($field['label']) }}</label>
            @if (! empty($field['help']))
                <div class="form-text">{{ __($field['help']) }}</div>
            @endif
        </div>
    @else
        <label class="form-label" for="f-{{ $name }}">{{ __($field['label']) }}@if ($required) <span class="text-danger">*</span>@endif</label>
        @if ($type === 'select')
            <select class="form-select @error($name) is-invalid @enderror" id="f-{{ $name }}" name="{{ $name }}" @disabled($readonly) @required($required) @if (! empty($field['search'])) data-search @endif>
                @unless ($required && $value !== null && $value !== '')
                    <option value="">{{ __('Choose…') }}</option>
                @endunless
                @foreach ($field['options'] ?? [] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        @elseif ($type === 'textarea')
            <textarea class="form-control @error($name) is-invalid @enderror" id="f-{{ $name }}" name="{{ $name }}" rows="2">{{ $value }}</textarea>
        @elseif ($type === 'date')
            <x-date :name="$name" :value="$value" :required="$required" id="f-{{ $name }}" class="{{ $errors->has($name) ? 'is-invalid' : '' }}" />
        @elseif ($type === 'decimal')
            <input type="text" inputmode="decimal" class="form-control @error($name) is-invalid @enderror" id="f-{{ $name }}" name="{{ $name }}" value="{{ $value }}" autocomplete="off" dir="ltr" @readonly($readonly) @required($required)>
        @else
            <input type="{{ $type }}" class="form-control @error($name) is-invalid @enderror" id="f-{{ $name }}" name="{{ $name }}" value="{{ $type === 'password' ? '' : $value }}" @if ($type === 'password') autocomplete="new-password" @endif @readonly($readonly) @required($required && $type !== 'password')>
        @endif
        @error($name)
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @if (! empty($field['help']))
            <div class="form-text">{{ __($field['help']) }}</div>
        @endif
    @endif
</div>
