@extends('layouts.app', ['title' => __('Recipe')])

@php
    $startLines = old('lines') ?? ($recipe ? $recipe->lines->map(fn ($l) => ['item_id' => $l->item_id, 'qty' => $l->qty])->all() : [[], [], []]);
@endphp

@section('content')
<x-page-header :title="$recipe ? __('Edit :name', ['name' => __('Recipe')]) : __('Add :name', ['name' => __('Recipe')])" icon="flask-vial" :crumbs="[[__('Recipes & Formulas'), route('recipes.index')], [__('Recipe')]]" />

<form method="POST" action="{{ $recipe ? route('recipes.update', ['id' => $recipe->id]) : route('recipes.store') }}">
    @csrf
    @if ($recipe)
        @method('PUT')
    @endif
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="f-item">{{ __('Product') }} <span class="text-danger">*</span></label>
                    <select class="form-select" id="f-item" name="item_id" {{ quick_add('items', __('New product'), 'items.create', ['type' => 'product']) }} required data-search>
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((string) old('item_id', $recipe?->item_id) === (string) $product->id)>{{ $product->label() }}</option>
                        @endforeach
                    </select>
                    @if ($products->isEmpty())
                        <div class="form-text text-danger">{{ __('Add the product first (Manufacturing > Final Products).') }}</div>
                    @endif
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="f-method">{{ __('How it is made') }}</label>
                    <select class="form-select" id="f-method" name="method">
                        @foreach ($methods as $key => $name)
                            <option value="{{ $key }}" @selected(old('method', $recipe?->method ?? 'liquid') === $key)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="f-weight">{{ __('Weight of one can or bag (kg)') }}</label>
                    <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control" id="f-weight" name="pack_weight" value="{{ old('pack_weight', $recipe ? qty($recipe->pack_weight) : '') }}" placeholder="20">
                    <div class="form-text">{{ __('For liquids the rest of the weight is water.') }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="f-extra">{{ __('Other cost of one (:base)', ['base' => $base]) }}</label>
                    <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control" id="f-extra" name="extra_cost" value="{{ old('extra_cost', $recipe ? \App\Support\Num::format($recipe->extra_cost, 4, true) : '') }}">
                    <div class="form-text">{{ __('Labour, electricity and other production cost.') }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="f-life">{{ __('Good for (months)') }}</label>
                    <input type="number" min="0" max="240" class="form-control" id="f-life" name="shelf_life_months" value="{{ old('shelf_life_months', $recipe?->shelf_life_months ?? 0) }}">
                    <div class="form-text">{{ __('Used to fill in the expiry date of a batch. 0 = no expiry.') }}</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="form-check form-switch mt-md-4 pt-md-2">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="f-active" name="is_active" value="1" @checked(old('is_active', $recipe?->is_active ?? true))>
                        <label class="form-check-label" for="f-active">{{ __('Active') }}</label>
                        <div class="form-text">{{ __('Switch off to stop using it. It disappears from pick lists, but its history stays.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><div><h5 class="card-title">{{ __('Ingredients for ONE can or bag') }}</h5><div class="small text-muted">{{ __('Write the dose of each raw material and the packing (1 can, 1 bag). Do not add water.') }}</div></div></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table w-100 keep-table" data-rows>
                    <thead><tr><th>{{ __('Raw material or packing') }}</th><th class="text-end">{{ __('Dose for one') }}</th><th>{{ __('Unit') }}</th><th></th></tr></thead>
                    <tbody data-rows-body>
                        @foreach ($startLines as $i => $line)
                            @include('production.recipe-row', ['i' => $i, 'line' => $line])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <template data-row-template>@include('production.recipe-row', ['i' => '__i__', 'line' => []])</template>
            <button type="button" class="btn btn-sm btn-outline-soft" data-add-row><x-icon name="plus" /> {{ __('Add ingredient') }}</button>
            @can('items.manage')
                <button type="button" class="btn btn-sm btn-outline-soft" data-quick-target="select[data-item]" {{ quick_add('items', __('New raw material'), 'items.create', ['type' => 'raw']) }}><x-icon name="plus" /> {{ __('New raw material') }}</button>
            @endcan
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <label class="form-label" for="f-notes">{{ __('How to make it (notes)') }}</label>
            <textarea class="form-control" id="f-notes" name="notes" rows="3">{{ old('notes', $recipe?->notes) }}</textarea>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
            <a class="btn btn-light-secondary" href="{{ route('recipes.index') }}">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
        </div>
    </div>
</form>
@endsection
