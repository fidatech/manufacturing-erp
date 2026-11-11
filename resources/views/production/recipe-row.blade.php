<tr data-row>
    <td>
        <select class="form-select" name="lines[{{ $i }}][item_id]" data-search data-item>
            <option value="">{{ __('Choose…') }}</option>
            @foreach ($materials as $material)
                <option value="{{ $material->id }}" data-unit="{{ $material->unit->symbol ?: $material->unit->name }}" @selected((string) ($line['item_id'] ?? '') === (string) $material->id)>{{ $material->label() }}</option>
            @endforeach
        </select>
    </td>
    <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][qty]" value="{{ isset($line['qty']) ? \App\Support\Num::format($line['qty'], 6, true) : '' }}" aria-label="{{ __('Quantity') }}"></td>
    <td class="text-muted" data-unit>{{ $line['unit'] ?? '' }}</td>
    <td class="text-end"><button type="button" class="btn btn-sm btn-light-danger" data-remove-row aria-label="{{ __('Remove') }}"><x-icon name="xmark" /></button></td>
</tr>
