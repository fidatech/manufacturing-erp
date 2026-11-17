@extends('layouts.app', ['title' => $heading])

@php
    use App\Support\Num;

    $multi = in_array($kind, ['in', 'out'], true);
    $labels = [
        'in' => [__('Received from'), __('Put the money in')],
        'out' => [__('Paid to'), __('Take the money from')],
        'expense' => [__('Spent on'), __('Take the money from')],
        'transfer' => [__('Move the money to'), __('Take the money from')],
    ][$kind];
    $tones = ['due' => 'is-due', 'owe' => 'is-owe', 'capital' => 'is-capital'];
    // words and colour of a balance on each side, so the screen can say "owes us" / "we owe" while choosing
    $sides = function ($account) use ($tones) {
        return 'data-pos="'.e($account->balanceWords('1')).'" data-neg="'.e($account->balanceWords('-1')).'"'
            .' data-pos-tone="'.($tones[$account->balanceTone('1')] ?? '').'" data-neg-tone="'.($tones[$account->balanceTone('-1')] ?? '').'"';
    };
@endphp

@section('content')
<x-page-header :title="$heading" icon="money-bill-wave" :crumbs="[[__('Money'), route('money.index')], [$heading]]" />

<form method="POST" action="{{ route('money.store', ['kind' => $kind]) }}" class="money-entry" @if ($multi) data-money-lines data-kind="{{ $kind }}" data-rates="{{ json_encode($rates) }}" data-texts="{{ json_encode(['Only' => __('Only'), 'Balance' => __('Balance'), 'Balance now' => __('Balance now'), 'Choose who first' => __('Choose who first'), 'Settled: nothing left between you.' => __('Settled: nothing left between you.')]) }}" @else data-money-form @endif>
    @csrf
    <input type="hidden" name="group" value="{{ $group }}">

    <div class="row g-4">
        <div class="{{ $multi ? 'col-12 col-xl-8' : 'col-12' }}">
            {{-- 1. who and when --}}
            <div class="card mb-4">
                <div class="card-body">
                    <div class="me-section"><span class="me-step">1</span><div><h5>{{ $labels[0] }}</h5><p>{{ __('The person or account and the date of the money.') }}</p></div></div>
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label class="form-label" for="f-other">{{ $labels[0] }} <span class="text-danger">*</span></label>
                            <select class="form-select @error('other_id') is-invalid @enderror" id="f-other" name="other_id" {{ $quickOther ?? '' }} required data-search data-currency-of="other">
                                <option value="">{{ __('Choose…') }}</option>
                                @foreach ($others as $account)
                                    <option value="{{ $account->id }}" data-cur="{{ $account->currency->code }}" data-cur-id="{{ $account->currency_id }}" data-balance="{{ $balances[$account->id]['own'] ?? '0.00' }}" {!! $sides($account) !!} @selected((string) old('other_id', request('account')) === (string) $account->id)>{{ $account->name }} ({{ $account->currency->code }}) · {{ __($account->type->name) }}</option>
                                @endforeach
                            </select>
                            @error('other_id')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            @if ($multi)
                                <div class="me-person" data-person-card hidden>
                                    <span class="badge-soft secondary" data-person-cur-badge></span>
                                    <span class="me-person-bal" data-person-now></span>
                                </div>
                            @endif
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="f-date">{{ __('Date') }} <span class="text-danger">*</span></label>
                            <x-date name="date" :value="old('date', today_iso())" id="f-date" required />
                            @error('date')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. the money --}}
            <div class="card mb-4">
                <div class="card-body">
                    @if ($multi)
                        <div class="me-section"><span class="me-step">2</span><div><h5>{{ __('The money') }}</h5><p>{{ __('Money in several currencies? Add a line for each. The rate of the day is filled in; change it if the money was exchanged at another rate.') }}</p></div></div>
                        {{-- several cash boxes, banks or sarafi in one go, each in its own currency with its own rate --}}
                        <div class="money-lines-box" data-rows>
                            <div class="money-line money-line-head" aria-hidden="true">
                                <div>{{ $labels[1] }}</div>
                                <div>{{ __('Amount') }}</div>
                                <div>{{ __('Exchange rate') }}</div>
                                <div>{{ __('Converted') }} <span data-person-cur-head></span></div>
                                <div></div>
                            </div>
                            <div data-rows-body="lines">
                                @foreach (old('lines', [[]]) as $i => $line)
                                    @include('money.line', ['i' => $i, 'line' => $line, 'moneyLabel' => $labels[1]])
                                @endforeach
                            </div>
                        </div>
                        <template data-row-template="lines">@include('money.line', ['i' => '__i__', 'line' => [], 'moneyLabel' => $labels[1]])</template>
                        <div class="money-lines-foot">
                            <button type="button" class="btn btn-sm btn-outline-soft" data-add-row="lines"><x-icon name="plus" class="me-1" /> {{ __('Add another currency') }}</button>
                            <div class="money-lines-total">
                                <span>{{ __('Total') }}</span>
                                <strong dir="ltr"><span data-lines-total>0.00</span> <span data-person-cur>—</span></strong>
                            </div>
                        </div>
                        @error('lines')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    @else
                        <div class="me-section"><span class="me-step">2</span><div><h5>{{ __('The money') }}</h5></div></div>
                        <div class="row g-3">
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
                                <input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control @error('amount') is-invalid @enderror" id="f-amount" name="amount" value="{{ old('amount') }}" required placeholder="0.00">
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
                        </div>
                    @endif
                </div>
            </div>

            {{-- 3. papers and note --}}
            <div class="card mb-4">
                <div class="card-body">
                    <div class="me-section"><span class="me-step">3</span><div><h5>{{ __('Details') }}</h5></div></div>
                    <div class="row g-3">
                        @if ($multi)
                            <div class="col-12 col-md-5">
                                <label class="form-label" for="f-reference">{{ $group === 'supplier' ? __('TT number') : __('Reference / TT number') }}</label>
                                <input type="text" class="form-control" id="f-reference" name="reference" value="{{ old('reference') }}" maxlength="100" dir="ltr">
                                <div class="form-text">{{ __('The number of the bank or sarafi transfer, a cheque number … It is printed on the receipt.') }}</div>
                            </div>
                        @endif
                        <div class="col-12 {{ $multi ? 'col-md-7' : '' }}">
                            <label class="form-label" for="f-description">{{ __('Note') }}</label>
                            <input type="text" class="form-control" id="f-description" name="description" value="{{ old('description') }}" maxlength="500">
                        </div>
                    </div>
                </div>
                @unless ($multi)
                    <div class="card-footer d-flex gap-2 justify-content-end">
                        <a class="btn btn-light-secondary" href="{{ route('money.index') }}">{{ __('Cancel') }}</a>
                        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
                    </div>
                @endunless
            </div>
        </div>

        @if ($multi)
            {{-- what this entry does to the person's account, before saving --}}
            <div class="col-12 col-xl-4">
                <div class="card money-summary">
                    <div class="card-body">
                        <div class="ms-head">
                            <span class="ms-icon {{ $kind === 'in' ? 'is-in' : 'is-out' }}"><x-icon :name="$kind === 'in' ? 'arrow-down' : 'arrow-up'" /></span>
                            <div>
                                <div class="ms-kind">{{ $heading }}</div>
                                <div class="ms-name" data-sum-person>{{ __('Choose who first') }}</div>
                            </div>
                        </div>
                        <dl class="ms-rows">
                            <div><dt>{{ __('Balance now') }}</dt><dd dir="ltr" data-sum-now>—</dd></div>
                            <div><dt>{{ $kind === 'in' ? __('Received') : __('Paid') }}</dt><dd dir="ltr" data-sum-this>—</dd></div>
                            <div class="ms-after"><dt>{{ __('Balance after') }}</dt><dd dir="ltr" data-sum-after>—</dd></div>
                        </dl>
                        <div class="ms-words" data-sum-words></div>
                        <ul class="ms-lines" data-sum-lines></ul>
                    </div>
                    <div class="card-footer d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
                        <a class="btn btn-light-secondary" href="{{ route('money.index') }}">{{ __('Cancel') }}</a>
                    </div>
                </div>
            </div>
        @endif
    </div>
</form>
@endsection
