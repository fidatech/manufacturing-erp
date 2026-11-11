<tr data-row>
    <td>
        <select class="form-select" name="lines[{{ $i }}][account_id]" data-search>
            <option value="">{{ __('Choose…') }}</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected((string) ($line['account_id'] ?? '') === (string) $account->id)>{{ $account->name }} ({{ $account->currency->code }}) · {{ __($account->type->name) }}</option>
            @endforeach
        </select>
    </td>
    <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][debit]" value="{{ $line['debit'] ?? '' }}"></td>
    <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][credit]" value="{{ $line['credit'] ?? '' }}"></td>
    <td class="text-end"><button type="button" class="btn btn-sm btn-light-danger" data-remove-row aria-label="{{ __('Remove') }}"><x-icon name="xmark" /></button></td>
</tr>
