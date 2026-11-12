@extends('layouts.app', ['title' => $title])

@section('content')
<x-page-header :title="$title" icon="right-left">
    @can('inventory.manage')
        <a class="btn btn-primary" href="{{ route('stock-docs.create', ['type' => $type]) }}"><x-icon name="plus" class="me-1" /> {{ __('Add :name', ['name' => $singular]) }}</a>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3 no-print">
            @include('partials.date-filter', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null])
            <div class="col-12 col-md-auto">
                <label class="form-label small mb-1">{{ __('Warehouse') }}</label>
                <select class="form-select" name="warehouse">
                    <option value="">{{ __('All warehouses') }}</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse'] ?? '') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('No.') }}</th><th>{{ __('Date') }}</th><th>{{ __('Warehouse') }}</th><th>{{ __('Items') }}</th><th>{{ __('Status') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($docs as $doc)
                    <tr>
                        <td class="text-nowrap"><a class="fw-semibold" href="{{ route('stock-docs.show', ['type' => $type, 'id' => $doc->id]) }}">{{ $doc->number }}</a></td>
                        <td class="text-nowrap">{{ fmt_date($doc->date) }}</td>
                        <td>{{ $doc->warehouse->name }}{{ $doc->toWarehouse ? ' → '.$doc->toWarehouse->name : '' }}</td>
                        <td>{{ $doc->lines_count }}</td>
                        <td><span class="badge-soft {{ $doc->status === 'cancelled' ? 'danger' : 'success' }}">{{ $doc->status === 'cancelled' ? __('Cancelled') : __('Done') }}</span></td>
                        <td class="text-end"><a class="btn btn-sm btn-light-secondary" href="{{ route('stock-docs.show', ['type' => $type, 'id' => $doc->id]) }}">{{ __('Open') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $docs->links('partials.pagination') }}
    </div>
</div>
@endsection
