<tr data-row>
    <td>
        <select class="form-select" name="lines[{{ $i }}][item_id]" data-search data-item>
            <option value="">{{ __('Choose…') }}</option>
            @foreach ($items as $item)
                <option value="{{ $item->id }}" data-unit="{{ $item->unit->symbol ?: $item->unit->name }}" @selected((string) ($line['item_id'] ?? '') === (string) $item->id)>{{ $item->label() }}</option>
            @endforeach
        </select>
    </td>
    <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][price]" value="{{ $line['price'] ?? '' }}" aria-label="{{ __('Price for this customer') }}"><span class="small text-muted" data-unit></span></td>
    <td class="text-end"><button type="button" class="btn btn-sm btn-light-danger" data-remove-row aria-label="{{ __('Remove') }}"><x-icon name="xmark" /></button></td>
</tr>
