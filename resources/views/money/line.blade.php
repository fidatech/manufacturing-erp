{{-- One money account of a "money in / money out" entry: where the money is, how much, and at which rate. --}}
<tr data-row>
    <td>
        <select class="form-select" name="lines[{{ $i }}][money_id]" data-money aria-label="{{ __('Money account') }}">
            <option value="">{{ __('Choose…') }}</option>
            @foreach ($moneyAccounts as $account)
                <option value="{{ $account->id }}" data-cur="{{ $account->currency->code }}" data-cur-id="{{ $account->currency_id }}" @if ($account->type->key === 'cash') data-cash @endif @selected((string) ($line['money_id'] ?? '') === (string) $account->id)>{{ $account->name }} ({{ $account->currency->code }})</option>
            @endforeach
        </select>
    </td>
    <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][amount]" value="{{ $line['amount'] ?? '' }}" data-line-amount aria-label="{{ __('Amount') }}"></td>
    <td>
        <div class="input-group input-group-sm money-rate" data-line-rate-box>
            <select class="form-select" name="lines[{{ $i }}][operation]" data-line-op aria-label="{{ __('Operating type') }}">
                <option value="multiply" @selected(($line['operation'] ?? '') === 'multiply')>×</option>
                <option value="divide" @selected(($line['operation'] ?? '') === 'divide')>÷</option>
            </select>
            <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][rate]" value="{{ $line['rate'] ?? '' }}" data-line-rate aria-label="{{ __('Exchange rate') }}">
        </div>
        <span class="small text-muted" data-line-same>{{ __('same currency') }}</span>
    </td>
    <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][other_amount]" value="{{ $line['other_amount'] ?? '' }}" data-line-other aria-label="{{ __('In the person\'s currency') }}"></td>
    <td class="text-end"><button type="button" class="btn btn-sm btn-light-danger" data-remove-row aria-label="{{ __('Remove') }}"><x-icon name="xmark" /></button></td>
</tr>
