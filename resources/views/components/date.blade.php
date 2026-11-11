{{--
    Date box. Always give and receive the Gregorian date (2026-10-27).
    Users on the Solar Hijri calendar see and type 1405/08/05; it is converted before saving.
--}}
@props(['name', 'value' => null, 'required' => false])
@php
    $dotted = trim(str_replace(['][', '[', ']'], ['.', '.', ''], $name), '.');
    $shamsi = calendar() === 'shamsi';
@endphp
@if ($shamsi)
    <input type="text" name="{{ $name }}" value="{{ input_date($value) }}" inputmode="numeric" autocomplete="off" placeholder="1405/08/05" dir="ltr" {{ $required ? 'required' : '' }} {{ $attributes->class('form-control') }}>
    <input type="hidden" name="_shamsi[]" value="{{ $dotted }}">
@else
    <input type="date" name="{{ $name }}" value="{{ input_date($value) }}" {{ $required ? 'required' : '' }} {{ $attributes->class('form-control') }}>
@endif
