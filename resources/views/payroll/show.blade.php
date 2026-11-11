@extends('layouts.app', ['title' => __('Salaries of :period', ['period' => $payroll->period])])

@section('content')
<x-page-header :title="__('Salaries of :period', ['period' => $payroll->period])" icon="money-check-dollar" :crumbs="[[__('Payroll'), route('payroll.index')], [$payroll->period]]">
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
    @can('hr.manage')
        <form method="POST" action="{{ route('payroll.destroy', ['id' => $payroll->id]) }}" class="d-inline" data-confirm="{{ __('Take back the salaries of :period?', ['period' => $payroll->period]) }}" data-confirm-text="{{ __('The month can then be done again. Payments already made stay on the employees\' accounts.') }}" data-confirm-ok="{{ __('Take it back') }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-light-danger">{{ __('Take back') }}</button>
        </form>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body">
        <p class="text-muted">{{ __('Date') }}: {{ fmt_date($payroll->date) }}{{ $payroll->notes ? ' · '.$payroll->notes : '' }}</p>
        <table class="table w-100 keep-table">
            <thead><tr><th>#</th><th>{{ __('Employee') }}</th><th class="text-end">{{ __('Salary') }}</th><th class="text-end">{{ __('Extra') }}</th><th class="text-end">{{ __('Cut') }}</th><th class="text-end">{{ __('Tax') }}</th><th class="text-end">{{ __('To pay') }}</th><th class="text-end">{{ __('Account now') }}</th><th class="no-print"></th></tr></thead>
            <tbody>
                @foreach ($payroll->lines as $line)
                    @php
                        $own = $balances[$line->employee_id]['own'] ?? '0';
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $line->employee->name }}<div class="small text-muted">{{ $line->note }}</div></td>
                        <td class="text-end">{{ money($line->salary) }}</td>
                        <td class="text-end">{{ money($line->bonus) }}</td>
                        <td class="text-end">{{ money($line->deduction) }}</td>
                        <td class="text-end">{{ money($line->tax) }}</td>
                        <td class="text-end fw-semibold text-nowrap">{{ money($line->net, $line->employee->currency->code) }}</td>
                        <td class="text-end text-nowrap small">{{ $line->employee->describeBalance($own) }}</td>
                        <td class="text-end no-print">
                            @if (\App\Support\Num::isNegative($own))
                                @can('money.manage')
                                    <a class="btn btn-sm btn-success" href="{{ route('money.create', ['kind' => 'out', 'group' => 'employee', 'account' => $line->employee_id]) }}">{{ __('Pay') }}</a>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
