@extends('layouts.app', ['title' => __('Attendance')])

@section('content')
<x-page-header :title="__('Attendance')" icon="calendar-check" :subtitle="__('Mark every employee once a day.')" />

<form method="GET" class="card mb-4">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-8 col-md-auto"><label class="form-label small mb-1">{{ __('Day') }}</label><x-date name="date" :value="$date" /></div>
            <div class="col-4 col-md-auto"><button type="submit" class="btn btn-light-secondary w-100">{{ __('Show') }}</button></div>
        </div>
    </div>
</form>

@if ($employees->isEmpty())
    <div class="alert alert-info">{{ __('Add employees first.') }}</div>
@else
    <form method="POST" action="{{ route('attendance.store') }}" class="card mb-4">
        @csrf
        <input type="hidden" name="date" value="{{ $date }}">
        <div class="card-header"><h5 class="card-title">{{ fmt_date($date) }}</h5></div>
        <div class="card-body">
            <table class="table w-100 keep-table">
                <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('Today') }}</th><th>{{ __('Note') }}</th></tr></thead>
                <tbody>
                    @foreach ($employees as $employee)
                        <tr>
                            <td>{{ $employee->name }}<div class="small text-muted">{{ $employee->metaValue('job_title') }}</div></td>
                            <td>
                                <select class="form-select" name="rows[{{ $employee->id }}][status]" aria-label="{{ __('Today') }}">
                                    <option value="">{{ __('Not marked') }}</option>
                                    @foreach ($statuses as $key => $name)
                                        <option value="{{ $key }}" @selected(($marked->get($employee->id)?->status ?? ($marked->isEmpty() ? 'present' : '')) === $key)>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="text" class="form-control" name="rows[{{ $employee->id }}][notes]" value="{{ $marked->get($employee->id)?->notes ?? '' }}" aria-label="{{ __('Note') }}"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @can('hr.manage')
            <div class="card-footer d-flex justify-content-end"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save attendance') }}</button></div>
        @endcan
    </form>

    <div class="card">
        <div class="card-header"><h5 class="card-title">{{ __('This month so far') }} ({{ $month }})</h5></div>
        <div class="card-body">
            <table class="table w-100 keep-table">
                <thead><tr><th>{{ __('Employee') }}</th>@foreach ($statuses as $name)<th class="text-end">{{ $name }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($employees as $employee)
                        <tr>
                            <td>{{ $employee->name }}</td>
                            @foreach (array_keys($statuses) as $key)
                                <td class="text-end">{{ $summary->get($employee->id)?->get($key) ?? 0 }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
