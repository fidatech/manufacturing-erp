@extends('layouts.app', ['title' => __('Payroll')])

@section('content')
<x-page-header :title="__('Payroll')" icon="money-check-dollar" :subtitle="__('Monthly salaries of the employees.')">
    @can('hr.manage')
        <a class="btn btn-primary" href="{{ route('payroll.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Salaries of a month') }}</a>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Month') }}</th><th>{{ __('Date') }}</th><th>{{ __('Employees') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($payrolls as $payroll)
                    <tr>
                        <td><a class="fw-semibold" href="{{ route('payroll.show', ['id' => $payroll->id]) }}">{{ $payroll->period }}</a></td>
                        <td>{{ fmt_date($payroll->date) }}</td>
                        <td>{{ $payroll->lines_count }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-light-secondary" href="{{ route('payroll.show', ['id' => $payroll->id]) }}">{{ __('Open') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $payrolls->links('partials.pagination') }}
    </div>
</div>
@endsection
