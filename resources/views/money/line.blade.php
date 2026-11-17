{{-- One money account of a "money in / money out" entry: where the money is, how much, and at which rate. --}}
<div class="money-line" data-row>
    <div class="ml-field ml-account">
        <label class="ml-label">{{ $moneyLabel }}</label>
        <select class="form-select" name="lines[{{ $i }}][money_id]" data-money aria-label="{{ $moneyLabel }}">
            <option value="">{{ __('Choose…') }}</option>
            @foreach ($moneyAccounts as $account)
                <option value="{{ $account->id }}" data-cur="{{ $account->currency->code }}" data-cur-id="{{ $account->currency_id }}" data-balance="{{ $balances[$account->id]['own'] ?? '0.00' }}" @if ($account->type->key === 'cash') data-cash @endif @selected((string) ($line['money_id'] ?? '') === (string) $account->id)>{{ $account->name }} ({{ $account->currency->code }})</option>
            @endforeach
        </select>
        <div class="ml-hint" data-line-available></div>
    </div>
    <div class="ml-field ml-amount">
        <label class="ml-label">{{ __('Amount') }}</label>
        <div class="input-group">
            <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][amount]" value="{{ $line['amount'] ?? '' }}" data-line-amount placeholder="0.00" aria-label="{{ __('Amount') }}">
            <span class="input-group-text ml-cur" data-line-cur>—</span>
        </div>
    </div>
    <div class="ml-field ml-rate">
        <label class="ml-label">{{ __('Exchange rate') }}</label>
        <div class="input-group money-rate" data-line-rate-box>
            <select class="form-select" name="lines[{{ $i }}][operation]" data-line-op aria-label="{{ __('Operating type') }}" title="{{ __('Operating type') }}">
                <option value="multiply" @selected(($line['operation'] ?? '') === 'multiply')>×</option>
                <option value="divide" @selected(($line['operation'] ?? '') === 'divide')>÷</option>
            </select>
            <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" name="lines[{{ $i }}][rate]" value="{{ $line['rate'] ?? '' }}" data-line-rate aria-label="{{ __('Exchange rate') }}">
        </div>
        <div class="ml-hint" dir="ltr" data-line-rate-hint></div>
        <div class="form-control ml-same" data-line-same><x-icon name="check" /> {{ __('same currency') }}</div>
    </div>
    <div class="ml-field ml-other">
        <label class="ml-label">{{ __('In the person\'s currency') }}</label>
        <div class="input-group">
            <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end fw-semibold" name="lines[{{ $i }}][other_amount]" value="{{ $line['other_amount'] ?? '' }}" data-line-other placeholder="0.00" aria-label="{{ __('In the person\'s currency') }}">
            <span class="input-group-text ml-cur" data-person-cur>—</span>
        </div>
    </div>
    <div class="ml-remove">
        <button type="button" class="btn btn-icon btn-light-danger" data-remove-row aria-label="{{ __('Remove') }}" title="{{ __('Remove') }}"><x-icon name="trash-can" /></button>
    </div>
</div>
