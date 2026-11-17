@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => __('Materials Check')])

@section('content')
<x-page-header :title="__('Materials Check')" icon="clipboard-check" :crumbs="[[__('Production Batches'), route('production.index')], [__('Materials Check')]]" :subtitle="__('How much raw material a batch needs, how much is in stock and what is short. Download it as PDF or Excel.')" />

@if (request()->boolean('print') && $recipe)
    @section('doc')
        <h2>{{ __('Materials Check') }}</h2>
        <div>{{ qty($qty ?: 1) }} × {{ $recipe->item->label() }}</div>
        <div>{{ $warehouseId ? $warehouses->firstWhere('id', $warehouseId)?->name : __('All warehouses') }} · {{ fmt_date(today_iso()) }}</div>
    @endsection
@endif

<form method="GET" class="card mb-4 no-print">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label" for="f-recipe">{{ __('Product') }}</label>
                <select class="form-select" id="f-recipe" name="recipe" required data-search>
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($recipes as $option)
                        <option value="{{ $option->id }}" @selected($recipe?->id === $option->id)>{{ $option->item->label() }}{{ $option->name !== $option->item->name ? ' · '.$option->name : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="f-qty">{{ __('How many to make?') }}</label>
                <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control" id="f-qty" name="qty" value="{{ $qty }}" placeholder="1">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="f-wh">{{ __('Raw materials from') }}</label>
                <select class="form-select" id="f-wh" name="warehouse">
                    <option value="">{{ __('All warehouses') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected($warehouseId === $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2"><button type="submit" class="btn btn-primary w-100">{{ __('Check') }}</button></div>
        </div>
    </div>
</form>

@if ($recipe)
    @php
        $shortCount = collect($rows)->where('enough', false)->count();
    @endphp
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ __('To make') }}</div><div class="fs-4 fw-bold">{{ qty($qty ?: 1) }}</div><div class="small">{{ $recipe->item->label() }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ __('Stock is enough for') }}</div><div class="fs-4 fw-bold {{ $canMake !== null && \App\Support\Num::cmp($canMake, $qty ?: 1) < 0 ? 'text-danger' : 'text-success' }}">{{ $canMake ?? '—' }}</div><div class="small">{{ $recipe->item->unit->name ?? '' }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ __('Materials short') }}</div><div class="fs-4 fw-bold {{ $shortCount ? 'text-danger' : 'text-success' }}">{{ $shortCount }} / {{ count($rows) }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ __('Cost of what is short (:base)', ['base' => $base]) }}</div><div class="fs-4 fw-bold">{{ money($costShort, $base) }}</div><div class="small text-muted">{{ __('At average cost') }}</div></div></div></div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="card-title">{{ qty($qty ?: 1) }} × {{ $recipe->item->label() }}</h5>
            @can('production.manage')
                @if ($qty && $warehouseId && ! $shortCount)
                    <a class="btn btn-sm btn-success no-print" href="{{ route('production.create', ['recipe' => $recipe->id, 'qty' => $qty, 'warehouse' => $warehouseId]) }}">{{ __('Record this production') }}</a>
                @endif
            @endcan
        </div>
        <div class="card-body">
            <table class="table table-hover w-100">
                <thead><tr><th>#</th><th>{{ __('Raw material or packing') }}</th><th>{{ __('Unit') }}</th><th class="text-end">{{ __('Needed') }}</th><th class="text-end">{{ __('In stock') }}</th><th class="text-end">{{ __('Short') }}</th><th class="text-end">{{ __('Cost of short (:base)', ['base' => $base]) }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $n => $row)
                        <tr>
                            <td>{{ $n + 1 }}</td>
                            <td>{{ $row['item']->label() }}</td>
                            <td>{{ $row['item']->unit->symbol ?: $row['item']->unit->name }}</td>
                            <td class="text-end text-nowrap">{{ qty($row['needed']) }}</td>
                            <td class="text-end text-nowrap">{{ qty($row['have']) }}</td>
                            <td class="text-end text-nowrap {{ $row['enough'] ? '' : 'text-danger fw-bold' }}">{{ $row['enough'] ? '—' : qty($row['short']) }}</td>
                            <td class="text-end text-nowrap">{{ $row['enough'] ? '—' : money($row['short_cost']) }}</td>
                            <td>@if ($row['enough'])<span class="badge-soft success">{{ __('Enough') }}</span>@else<span class="badge-soft danger">{{ __('Short') }}</span>@endif</td>
                        </tr>
                    @endforeach
                    @if (\App\Support\Num::isPositive($water))
                        <tr class="text-muted"><td></td><td>{{ __('Water (not kept in stock)') }}</td><td>kg</td><td class="text-end">{{ qty($water) }}</td><td></td><td></td><td></td><td></td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="card"><div class="card-body text-center text-muted py-5" data-no-export>{{ __('Choose a product and how many to make.') }}</div></div>
@endif
@endsection
