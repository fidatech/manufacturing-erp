@extends('layouts.app', ['title' => $heading])

@php
    $multi = in_array($kind, ['in', 'out'], true);
    $labels = [
        'in' => [__('Received from'), __('Put the money in')],
        'out' => [__('Paid to'), __('Take the money from')],
        'expense' => [__('Spent on'), __('Take the money from')],
        'transfer' => [__('Move the money to'), __('Take the money from')],
    ][$kind];
@endphp

@section('content')
<x-page-header :title="$heading" icon="money-bill-wave" :crumbs="[[__('Money'), route('money.index')], [$heading]]" />

<form method="POST" action="{{ route('money.store', ['kind' => $kind]) }}" class="card" @if ($multi) data-money-lines data-rates="{{ json_encode($rates) }}" @else data-money-form @endif>
    @csrf
    <input type="hidden" name="group" value="{{ $group }}">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="f-date">{{ __('Date') }} <span class="text-danger">*</span></label>
                <x-date name="date" :value="old('date', today_iso())" id="f-date" required />
                @error('date')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12 col-md-8">
                <label class="form-label" for="f-other">{{ $labels[0] }} <span class="text-danger">*</span></label>
                <select class="form-select @error('other_id') is-invalid @enderror" id="f-other" name="other_id" {{ $quickOther ?? '' }} required data-search data-currency-of="other">
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($others as $account)
                        <option value="{{ $account->id }}" data-cur="{{ $account->currency->code }}" data-cur-id="{{ $account->currency_id }}" @selected((string) old('other_id', request('account')) === (string) $account->id)>{{ $account->name }} ({{ $account->currency->code }}) · {{ __($account->type->name) }}</option>
                    @endforeach
                </select>
                @error('other_id')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            @if ($multi)
                {{-- several cash boxes, banks or sarafi in one go, each in its own currency with its own rate --}}
                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table w-100 keep-table money-lines" data-rows>
                            <thead>
                                <tr>
                                    <th>{{ $labels[1] }}</th>
                                    <th class="text-end">{{ __('Amount') }}</th>
                                    <th class="text-end">{{ __('Exchange rate') }}</th>
                                    <th class="text-end">{{ __('In the person\'s currency') }} <span data-cur-label="other"></span></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-rows-body="lines">
                                @foreach (old('lines', [[]]) as $i => $line)
                                    @include('money.line', ['i' => $i, 'line' => $line])
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td colspan="3">{{ __('Total') }}</td>
                                    <td class="text-end text-nowrap" dir="ltr"><span data-lines-total>0.00</span> <span data-cur-label="other"></span></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <template data-row-template="lines">@include('money.line', ['i' => '__i__', 'line' => []])</template>
                    <button type="button" class="btn btn-sm btn-outline-soft" data-add-row="lines"><x-icon name="plus" /> {{ __('Add another currency') }}</button>
                    <div class="form-text">{{ __('Money in several currencies? Add a line for each. The rate of the day is filled in; change it if the money was exchanged at another rate.') }}</div>
                    @error('lines')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            @else
                <div class="col-12 col-md-4">
                    <label class="form-label" for="f-money">{{ $labels[1] }} <span class="text-danger">*</span></label>
                    <select class="form-select @error('money_id') is-invalid @enderror" id="f-money" name="money_id" required data-currency-of="money">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($moneyAccounts as $account)
                            <option value="{{ $account->id }}" data-cur="{{ $account->currency->code }}" @if ($account->type->key === 'cash') data-cash @endif @selected((string) old('money_id') === (string) $account->id)>{{ $account->name }} ({{ $account->currency->code }})</option>
                        @endforeach
                    </select>
                    @error('money_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="f-amount">{{ __('Amount') }} <span data-cur-label="money" class="text-muted"></span> <span class="text-danger">*</span></label>
                    <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control @error('amount') is-invalid @enderror" id="f-amount" name="amount" value="{{ old('amount') }}" required>
                    @error('amount')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-md-4" data-other-amount hidden>
                    <label class="form-label" for="f-other-amount">{{ __('Same amount in') }} <span data-cur-label="other"></span></label>
                    <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control @error('other_amount') is-invalid @enderror" id="f-other-amount" name="other_amount" value="{{ old('other_amount') }}">
                    <div class="form-text">{{ __('The two accounts use different currencies. Leave empty to use the saved exchange rate.') }}</div>
                    @error('other_amount')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            @endif
            @if ($multi)
                <div class="col-12 col-md-4">
                    <label class="form-label" for="f-reference">{{ $group === 'supplier' ? __('TT number') : __('Reference / TT number') }}</label>
                    <input type="text" class="form-control" id="f-reference" name="reference" value="{{ old('reference') }}" maxlength="100" dir="ltr">
                    <div class="form-text">{{ __('The number of the bank or sarafi transfer, a cheque number … It is printed on the receipt.') }}</div>
                </div>
            @endif
            <div class="col-12 {{ $multi ? 'col-md-8' : '' }}">
                <label class="form-label" for="f-description">{{ __('Note') }}</label>
                <input type="text" class="form-control" id="f-description" name="description" value="{{ old('description') }}" maxlength="500">
            </div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('money.index') }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>
@endsection
