@extends('layouts.app', ['title' => __('Attendance').' '.$period])

@php
    use App\Support\Num;

    $show = fn ($value) => Num::format($value ?? 0, 1, true);
    // holidays count as present: the total of a line is the present days plus the holidays
    $total = fn (array $line) => Num::format(Num::add($line['present'], $line['holidays']), 1, true);
    $icons = ['present' => ['user-check', 'success'], 'absent' => ['user-xmark', 'danger'], 'leave' => ['umbrella-beach', 'warning'], 'holidays' => ['calendar-day', 'info']];
@endphp

@if (request()->boolean('print'))
    @section('doc')
        <h2>{{ __('Attendance') }}</h2>
        <div>{{ $period }}</div>
        <div>{{ __(':days days in the month', ['days' => $daysInMonth]) }}</div>
    @endsection
@endif

@section('content')
<x-page-header :title="__('Attendance')" icon="calendar-check" :subtitle="__('One sheet for each month: the days every employee was present, absent, on leave, and the holidays.')">
    @unless ($employees->isEmpty())
        <a class="btn btn-outline-soft" href="{{ request()->fullUrlWithQuery(['month' => $period, 'export' => 'xlsx']) }}"><x-icon name="file-excel" class="me-1" /> Excel</a>
        <a class="btn btn-outline-soft" href="{{ request()->fullUrlWithQuery(['month' => $period, 'print' => 1, 'pdf' => 1]) }}" target="_blank" rel="noopener"><x-icon name="file-pdf" class="me-1" /> PDF</a>
    @endunless
</x-page-header>

<div class="card mb-4 no-print" data-no-export>
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto"><a class="btn btn-light-secondary" href="{{ route('attendance.index', ['month' => $previous]) }}" aria-label="{{ __('Previous') }}"><x-icon name="chevron-left" /></a></div>
            <div class="col col-md-auto">
                <label class="form-label small mb-1" for="f-month">{{ __('Month') }}</label>
                <input type="month" class="form-control" id="f-month" name="month" value="{{ $period }}" max="{{ substr(today_iso(), 0, 7) }}">
            </div>
            <div class="col-auto"><a class="btn btn-light-secondary" href="{{ route('attendance.index', ['month' => $next]) }}" aria-label="{{ __('Next') }}"><x-icon name="chevron-right" /></a></div>
            <div class="col-auto"><button type="submit" class="btn btn-primary">{{ __('Show') }}</button></div>
        </form>
    </div>
</div>

@if ($employees->isEmpty())
    <div class="alert alert-info">{{ __('Add employees first.') }}</div>
@else
    <div class="row g-3 mb-4 no-print">
        @foreach ($columns as $key => $name)
            <div class="col-6 col-xl-3">
                <div class="card stat-card h-100 mb-0">
                    <div class="sc-top">
                        <div><div class="sc-label">{{ $name }}</div><div class="sc-value">{{ Num::format($totals[$key], 1, true) }}</div></div>
                        <span class="sc-icon icon-soft {{ $icons[$key][1] }}"><x-icon :name="$icons[$key][0]" /></span>
                    </div>
                    <div class="sc-foot">{{ __('All employees, :month', ['month' => $period]) }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($editing)
        <form method="POST" action="{{ route('attendance.store') }}" class="card">
            @csrf
            <input type="hidden" name="month" value="{{ $period }}">
            <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <h5 class="card-title mb-0">{{ $period }}</h5>
                <span class="badge-soft secondary">{{ __(':days days in the month', ['days' => $daysInMonth]) }}</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table w-100 keep-table attendance-table">
                        <thead>
                            <tr>
                                <th>{{ __('Employee') }}</th>
                                @foreach ($columns as $name)
                                    <th class="text-end">{{ $name }}</th>
                                @endforeach
                                <th class="text-end">{{ __('Total') }}</th>
                                <th>{{ __('Note') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employees as $employee)
                                @php
                                    $row = $rows->get($employee->id);
                                    $line = [];
                                    foreach (array_keys($columns) as $key) {
                                        $line[$key] = old('rows.'.$employee->id.'.'.$key, $row ? $row->{$key} : $usual[$key]) ?? 0;
                                    }
                                @endphp
                                <tr data-days-row data-days-month="{{ $daysInMonth }}">
                                    <td>{{ $employee->name }}<div class="small text-muted">{{ $employee->metaValue('job_title') }}</div></td>
                                    @foreach ($columns as $key => $name)
                                        <td><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control text-end" data-days="{{ $key }}" name="rows[{{ $employee->id }}][{{ $key }}]"
                                                   value="{{ is_numeric($line[$key]) ? $show($line[$key]) : $line[$key] }}" aria-label="{{ $name }}"></td>
                                    @endforeach
                                    <td class="text-end fw-semibold text-nowrap"><span data-days-total>{{ is_numeric($line['present']) && is_numeric($line['holidays']) ? $total($line) : '' }}</span> / {{ $daysInMonth }}@unless ($row) <div class="small fw-normal text-muted">{{ __('not saved yet') }}</div>@endunless</td>
                                    <td><input type="text" class="form-control" name="rows[{{ $employee->id }}][notes]" value="{{ old('rows.'.$employee->id.'.notes', $row?->notes) }}" maxlength="255" aria-label="{{ __('Note') }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="form-text">{{ __('Every line starts as the usual month: Fridays are holidays and the other days are present. Write the absent or leave days and the present days and the total change by themselves. Holidays count as present in the total. Half days can be written as 0.5.') }}</div>
            </div>
            <div class="card-footer d-flex justify-content-end"><button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save attendance') }}</button></div>
        </form>
    @else
        <div class="card">
            <div class="card-body">
                <table class="table w-100 keep-table">
                    <thead>
                        <tr>
                            <th>{{ __('Employee') }}</th>
                            @foreach ($columns as $name)
                                <th class="text-end">{{ $name }}</th>
                            @endforeach
                            <th class="text-end">{{ __('Total') }}</th>
                            <th>{{ __('Note') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $employee)
                            @php $row = $rows->get($employee->id); @endphp
                            <tr>
                                <td>{{ $employee->name }}</td>
                                @foreach (array_keys($columns) as $key)
                                    <td class="text-end">{{ Num::format($row?->{$key} ?? 0, 1, true) }}</td>
                                @endforeach
                                <td class="text-end fw-semibold">{{ $row ? $total(['present' => $row->present, 'holidays' => $row->holidays]) : '0' }}</td>
                                <td>{{ $row?->notes }}</td>
                            </tr>
                        @endforeach
                        <tr class="fw-bold">
                            <td>{{ __('Total') }}</td>
                            @foreach (array_keys($columns) as $key)
                                <td class="text-end">{{ Num::format($totals[$key], 1, true) }}</td>
                            @endforeach
                            <td class="text-end">{{ $total($totals) }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endif
@endsection
