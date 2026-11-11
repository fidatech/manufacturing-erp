@extends('layouts.app', ['title' => __('Salaries of a month')])

@section('content')
<x-page-header :title="__('Salaries of :period', ['period' => $period])" icon="money-check-dollar" :crumbs="[[__('Payroll'), route('payroll.index')], [$period]]" :subtitle="__('Check the list, change what is different this month and save. Each employee\'s pay is added to his or her account.')" />

<form method="GET" class="card mb-4">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-8 col-md-auto">
                <label class="form-label small mb-1" for="f-pick">{{ __('Month') }}</label>
                <input type="month" class="form-control" id="f-pick" name="period" value="{{ $period }}">
            </div>
            <div class="col-4 col-md-auto"><button type="submit" class="btn btn-light-secondary w-100">{{ __('Show') }}</button></div>
        </div>
    </div>
</form>

@if ($exists)
    <div class="alert alert-warning">{{ __('The salaries of this month were already saved.') }}</div>
@elseif ($employees->isEmpty())
    <div class="alert alert-info">{{ __('Add employees first, with their monthly salary.') }}</div>
@else
    <form method="POST" action="{{ route('payroll.store') }}" class="card">
        @csrf
        <input type="hidden" name="period" value="{{ $period }}">
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="f-date">{{ __('Date') }} <span class="text-danger">*</span></label>
                    <x-date name="date" :value="old('date', today_iso())" id="f-date" required />
                </div>
                <div class="col-12 col-md-9">
                    <label class="form-label" for="f-notes">{{ __('Notes') }}</label>
                    <input type="text" class="form-control" id="f-notes" name="notes" value="{{ old('notes') }}">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table w-100 keep-table">
                    <thead><tr><th></th><th>{{ __('Employee') }}</th><th class="text-end">{{ __('Salary') }}</th><th class="text-end">{{ __('Extra') }}</th><th class="text-end">{{ __('Cut') }}</th><th class="text-end">{{ __('Tax') }}</th><th>{{ __('Note') }}</th><th class="text-end">{{ __('Account now') }}</th></tr></thead>
                    <tbody>
                        @foreach ($employees as $i => $employee)
                            <tr>
                                <td>
                                    <input type="hidden" name="lines[{{ $i }}][employee_id]" value="{{ $employee->id }}">
                                    <input class="form-check-input" type="checkbox" name="lines[{{ $i }}][include]" value="1" aria-label="{{ __('Include') }}" @checked(old("lines.$i.include", '1'))>
                                </td>
                                <td>{{ $employee->name }}<div class="small text-muted">{{ $employee->metaValue('job_title') }} · {{ $employee->currency->code }}</div></td>
                                <td><input type="text" inputmode="decimal" dir="ltr" class="form-control text-end" name="lines[{{ $i }}][salary]" value="{{ old("lines.$i.salary", $employee->metaValue('salary')) }}" aria-label="{{ __('Salary') }}"></td>
                                <td><input type="text" inputmode="decimal" dir="ltr" class="form-control text-end" name="lines[{{ $i }}][bonus]" value="{{ old("lines.$i.bonus", ($a = $adjustments->get($employee->id)?->get('allowance')) ? \App\Support\Num::money($a) : '') }}" aria-label="{{ __('Extra') }}"></td>
                                <td><input type="text" inputmode="decimal" dir="ltr" class="form-control text-end" name="lines[{{ $i }}][deduction]" value="{{ old("lines.$i.deduction", ($d = $adjustments->get($employee->id)?->get('deduction')) ? \App\Support\Num::money($d) : '') }}" aria-label="{{ __('Cut') }}"></td>
                                <td><input type="text" inputmode="decimal" dir="ltr" class="form-control text-end" name="lines[{{ $i }}][tax]" value="{{ old("lines.$i.tax", $employee->metaValue('salary_tax')) }}" aria-label="{{ __('Tax') }}"></td>
                                <td><input type="text" class="form-control" name="lines[{{ $i }}][note]" value="{{ old("lines.$i.note") }}" aria-label="{{ __('Note') }}"></td>
                                <td class="text-end text-nowrap small">{{ $employee->describeBalance($balances[$employee->id]['own'] ?? '0') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="form-text">{{ __('Extra = allowances, overtime or bonus. Cut = deductions, absence or fine. Monthly allowances and deductions are filled in by themselves. Advances are not written here: they are already on the employee\'s account.') }}</div>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
            <a class="btn btn-light-secondary" href="{{ route('payroll.index') }}">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save salaries') }}</button>
        </div>
    </form>
@endif
@endsection
