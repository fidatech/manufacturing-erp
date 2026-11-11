@extends('layouts.app', ['title' => __('Recipes & Formulas')])

@section('content')
<x-page-header :title="__('Recipes & Formulas')" icon="flask-vial" :subtitle="__('What goes into one can or one bag of every product, and what it costs today.')">
    @can('production.manage')
        <a class="btn btn-primary" href="{{ route('recipes.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Add :name', ['name' => __('Recipe')]) }}</a>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col"><input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search…') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit"><x-icon name="magnifying-glass" /></button></div>
        </form>
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Product') }}</th><th>{{ __('How it is made') }}</th><th>{{ __('Ingredients') }}</th><th class="text-end">{{ __('Cost of one (:base)', ['base' => $base]) }}</th><th class="text-end">{{ __('Sale price') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($recipes as $recipe)
                    @php
                        $cost = $recipe->unitCost();
                        $price = $recipe->item->sale_price;
                    @endphp
                    <tr>
                        <td>{{ $recipe->item->label() }}@unless ($recipe->is_active) <span class="badge-soft warning">{{ __('Off') }}</span>@endunless@if ($recipe->name !== $recipe->item->name)<div class="small text-muted">{{ $recipe->name }}</div>@endif</td>
                        <td>{{ $recipe->method === 'liquid' ? __('Liquid') : __('Powder') }}@if (\App\Support\Num::isPositive($recipe->pack_weight)) · {{ qty($recipe->pack_weight) }} kg @endif</td>
                        <td>{{ $recipe->lines->count() }}@if (\App\Support\Num::isPositive($recipe->water())) <span class="small text-muted">+ {{ __('water') }} {{ qty($recipe->water()) }} kg</span>@endif</td>
                        <td class="text-end fw-semibold">{{ \App\Support\Num::format($cost, 2) }}</td>
                        <td class="text-end">{{ money($price) }}@if (\App\Support\Num::isPositive($price))<div class="small {{ \App\Support\Num::cmp($price, $cost) >= 0 ? 'text-success' : 'text-danger' }}">{{ __('profit') }} {{ money(\App\Support\Num::sub($price, $cost)) }}</div>@endif</td>
                        <td class="text-end text-nowrap">
                            @can('production.manage')
                                <a class="btn btn-sm btn-success" href="{{ route('production.create', ['recipe' => $recipe->id]) }}">{{ __('Produce') }}</a>
                                <a class="btn btn-sm btn-light-secondary" href="{{ route('recipes.edit', ['id' => $recipe->id]) }}">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('recipes.destroy', ['id' => $recipe->id]) }}" class="d-inline" data-confirm="{{ __('Delete this?') }}" data-confirm-ok="{{ __('Delete') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light-danger">{{ __('Delete') }}</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $recipes->links('partials.pagination') }}
    </div>
</div>
@endsection
