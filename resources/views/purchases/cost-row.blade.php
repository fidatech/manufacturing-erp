<tr data-row>
    <td><input type="text" class="form-control" name="costs[{{ $i }}][name]" value="{{ $cost['name'] ?? '' }}" placeholder="{{ __('Transport, customs, commission…') }}"></td>
    <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="costs[{{ $i }}][amount]" value="{{ $cost['amount'] ?? '' }}"></td>
    <td>
        <select class="form-select" name="costs[{{ $i }}][paid_from_id]">
            <option value="">{{ __('Choose…') }}</option>
            @foreach ($moneyAccounts as $account)
                <option value="{{ $account->id }}" @selected((string) ($cost['paid_from_id'] ?? '') === (string) $account->id)>{{ $account->name }} ({{ $account->currency->code }})</option>
            @endforeach
        </select>
    </td>
    <td class="text-end"><button type="button" class="btn btn-sm btn-light-danger" data-remove-row aria-label="{{ __('Remove') }}"><x-icon name="xmark" /></button></td>
</tr>
