{{-- Warehouse choice inside the period bar of the stock card. --}}
<div class="col-12 col-md-auto">
    <label class="form-label small mb-1">{{ __('Warehouse') }}</label>
    <select class="form-select" name="warehouse">
        <option value="">{{ __('All warehouses') }}</option>
        @foreach ($warehouses as $warehouse)
            <option value="{{ $warehouse->id }}" @selected((string) ($filters['warehouse'] ?? '') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
        @endforeach
    </select>
</div>
