<tr data-row>
    <td>
        <select class="form-select" name="lines[{{ $i }}][item_id]" data-search data-item>
            <option value="">{{ __('Choose…') }}</option>
            @foreach ($items as $item)
                <option value="{{ $item->id }}" data-unit="{{ $item->unit->symbol ?: $item->unit->name }}" data-price="{{ \App\Support\Num::isZero($item->sale_price) ? '' : \App\Support\Num::format($item->sale_price, 2, true) }}" @selected((string) ($line['item_id'] ?? '') === (string) $item->id)>{{ $item->label() }}</option>
            @endforeach
        </select>
    </td>
    <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][qty]" value="{{ isset($line['qty']) ? qty($line['qty']) : '' }}" data-qty aria-label="{{ __('Quantity') }}"><span class="small text-muted" data-unit></span></td>
    <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][price]" value="{{ isset($line['price']) ? \App\Support\Num::format($line['price'], 6, true) : '' }}" data-price aria-label="{{ __('Price') }}" placeholder="0 = {{ __('free') }}"></td>
    <td class="text-end text-nowrap" data-line-total>0.00</td>
    <td class="text-end"><button type="button" class="btn btn-sm btn-light-danger" data-remove-row aria-label="{{ __('Remove') }}"><x-icon name="xmark" /></button></td>
</tr>
