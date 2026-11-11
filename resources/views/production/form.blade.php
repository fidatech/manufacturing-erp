@extends('layouts.app', ['title' => __('Record production')])

@section('content')
<x-page-header :title="__('Record production')" icon="industry" :crumbs="[[__('Production Batches'), route('production.index')], [__('Record production')]]" :subtitle="__('Say what was made and how many. The raw materials leave stock and the finished product enters it.')" />

<form method="GET" class="card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label" for="f-recipe">{{ __('What was made?') }}</label>
                <select class="form-select" id="f-recipe" name="recipe" required data-search>
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($recipes as $option)
                        <option value="{{ $option->id }}" @selected($recipe?->id === $option->id)>{{ $option->item->label() }}{{ $option->name !== $option->item->name ? ' · '.$option->name : '' }}</option>
                    @endforeach
                </select>
                @if ($recipes->isEmpty())
                    <div class="form-text text-danger">{{ __('Add a recipe first (Manufacturing > Recipes & Formulas).') }}</div>
                @endif
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="f-qty">{{ __('How many?') }}</label>
                <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control" id="f-qty" name="qty" value="{{ $qty }}" required placeholder="50">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="f-wh">{{ __('Raw materials from') }}</label>
                <select class="form-select" id="f-wh" name="warehouse" required>
                    @if ($warehouses->count() !== 1)
                        <option value="">{{ __('Choose…') }}</option>
                    @endif
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected($warehouseId === $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2"><button type="submit" class="btn btn-light-secondary w-100">{{ __('Show materials') }}</button></div>
        </div>
    </div>
</form>

@if ($recipe && $qty && $warehouseId)
    @php
        $short = collect($needs)->where('enough', false)->count();
    @endphp
    <form method="POST" action="{{ route('production.store') }}" class="card">
        @csrf
        <input type="hidden" name="recipe_id" value="{{ $recipe->id }}">
        <input type="hidden" name="qty" value="{{ $qty }}">
        <input type="hidden" name="warehouse_id" value="{{ $warehouseId }}">
        <div class="card-header"><h5 class="card-title">{{ qty($qty) }} × {{ $recipe->item->label() }}</h5></div>
        <div class="card-body">
            @if ($short)
                <div class="alert alert-danger">{{ __('Some raw materials are not enough in this warehouse. Buy or transfer them first, or correct the quantity used.') }}</div>
            @endif
            <table class="table w-100 keep-table">
                <thead><tr><th>{{ __('Raw material or packing') }}</th><th class="text-end">{{ __('Recipe needs') }}</th><th class="text-end">{{ __('In stock') }}</th><th class="text-end">{{ __('Really used') }}</th></tr></thead>
                <tbody>
                    @foreach ($needs as $need)
                        <tr>
                            <td>{{ $need['item']->label() }}</td>
                            <td class="text-end text-nowrap">{{ qty($need['needed']) }} {{ $need['item']->unit->symbol }}</td>
                            <td class="text-end text-nowrap {{ $need['enough'] ? '' : 'text-danger fw-bold' }}">{{ qty($need['have']) }}</td>
                            <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="used[{{ $need['item']->id }}]" value="{{ old('used.'.$need['item']->id, qty($need['needed'])) }}" aria-label="{{ __('Really used') }}"></td>
                        </tr>
                    @endforeach
                    @if (\App\Support\Num::isPositive($water))
                        <tr class="text-muted"><td>{{ __('Water (not kept in stock)') }}</td><td class="text-end">{{ qty($water) }} kg</td><td></td><td></td></tr>
                    @endif
                </tbody>
            </table>

            <div class="row g-3 mt-1">
                <div class="col-6 col-md-3">
                    <label class="form-label" for="f-date">{{ __('Made on') }} <span class="text-danger">*</span></label>
                    <x-date name="date" :value="old('date', today_iso())" id="f-date" required />
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="f-to">{{ __('Put the product in') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="f-to" name="to_warehouse_id" required>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) old('to_warehouse_id', $warehouseId) === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="f-batch">{{ __('Batch number') }}</label>
                    <input type="text" class="form-control" id="f-batch" name="batch_no" value="{{ old('batch_no') }}" maxlength="60" placeholder="{{ __('Empty = the system gives one') }}">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="f-expiry">{{ __('Expires on') }}</label>
                    <x-date name="expiry_date" :value="old('expiry_date')" id="f-expiry" />
                    <div class="form-text">{{ $recipe->shelf_life_months ? __('Empty = :months months after it was made.', ['months' => $recipe->shelf_life_months]) : __('Empty = no expiry.') }}</div>
                    @error('expiry_date')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="f-notes">{{ __('Notes') }}</label>
                    <input type="text" class="form-control" id="f-notes" name="notes" value="{{ old('notes') }}">
                </div>
            </div>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
            <a class="btn btn-light-secondary" href="{{ route('production.index') }}">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save production') }}</button>
        </div>
    </form>
@endif
@endsection
