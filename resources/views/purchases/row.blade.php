<tr data-row>
    <td>
        <select class="form-select" name="lines[{{ $i }}][item_id]" data-search data-item>
            <option value="">{{ __('Choose…') }}</option>
            @foreach ($items as $item)
                <option value="{{ $item->id }}" data-unit="{{ $item->unit->symbol ?: $item->unit->name }}" @selected((string) ($line['item_id'] ?? '') === (string) $item->id)>{{ $item->label() }}</option>
            @endforeach
        </select>
    </td>
    <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][qty]" value="{{ isset($line['qty']) ? qty($line['qty']) : '' }}" data-qty aria-label="{{ __('Quantity') }}"><span class="small text-muted" data-unit></span></td>
    <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][price]" value="{{ isset($line['price']) ? \App\Support\Num::format($line['price'], 6, true) : '' }}" data-price aria-label="{{ __('Price') }}"></td>
    <td class="text-end text-nowrap" data-line-total>0.00</td>
    @if (in_array($type, ['invoice', 'return'], true))
        <td><input type="text" class="form-control" name="lines[{{ $i }}][batch_no]" value="{{ $line['batch_no'] ?? '' }}" aria-label="{{ __('Batch') }}"></td>
        @if ($type === 'invoice')
            <td><x-date name="lines[{{ $i }}][mfg_date]" :value="$line['mfg_date'] ?? null" /></td>
            <td><x-date name="lines[{{ $i }}][expiry_date]" :value="$line['expiry_date'] ?? null" /></td>
        @endif
    @endif
    <td class="text-end"><button type="button" class="btn btn-sm btn-light-danger" data-remove-row aria-label="{{ __('Remove') }}"><x-icon name="xmark" /></button></td>
</tr>
