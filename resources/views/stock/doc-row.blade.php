<tr data-row>
    <td>
        <select class="form-select" name="lines[{{ $i }}][item_id]" data-search data-item>
            <option value="">{{ __('Choose…') }}</option>
            @foreach ($items as $item)
                <option value="{{ $item->id }}" data-unit="{{ $item->unit->symbol ?: $item->unit->name }}" @selected((string) ($line['item_id'] ?? '') === (string) $item->id)>{{ $item->label() }}</option>
            @endforeach
        </select>
    </td>
    @if ($type === 'count')
        <td class="text-end text-nowrap text-muted">{{ isset($line['system']) ? qty($line['system']) : '' }}</td>
    @endif
    <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][qty]" value="{{ $line['qty'] ?? '' }}" aria-label="{{ __('Quantity') }}"><span class="small text-muted" data-unit></span></td>
    @if ($type === 'opening')
        <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][unit_cost]" value="{{ $line['unit_cost'] ?? '' }}" aria-label="{{ __('Cost each') }}"></td>
        <td><input type="text" class="form-control" name="lines[{{ $i }}][batch_no]" value="{{ $line['batch_no'] ?? '' }}" aria-label="{{ __('Batch') }}"></td>
        <td><x-date name="lines[{{ $i }}][mfg_date]" :value="$line['mfg_date'] ?? null" /></td>
        <td><x-date name="lines[{{ $i }}][expiry_date]" :value="$line['expiry_date'] ?? null" /></td>
    @endif
    <td class="text-end"><button type="button" class="btn btn-sm btn-light-danger" data-remove-row aria-label="{{ __('Remove') }}"><x-icon name="xmark" /></button></td>
</tr>
