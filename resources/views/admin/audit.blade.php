@extends('layouts.app', ['title' => __('Audit Log')])

@section('content')
<x-page-header :title="__('Audit Log')" icon="clock-rotate-left" />

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3">
            @include('partials.date-filter', ['from' => request('from'), 'to' => request('to')])
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1">{{ __('User') }}</label>
                <select class="form-select" name="user">
                    <option value="">{{ __('Everyone') }}</option>
                    @foreach ($users as $id => $name)
                        <option value="{{ $id }}" @selected((string) request('user') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col">
                <label class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" class="form-control" name="q" value="{{ request('q') }}">
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('When') }}</th><th>{{ __('User') }}</th><th>{{ __('Did') }}</th><th>{{ __('What') }}</th><th>{{ __('Details') }}</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($log->created_at) }} {{ $log->created_at->format('H:i') }}</td>
                        <td>{{ $log->user?->name ?? '-' }}</td>
                        <td>{{ __($log->action) }}</td>
                        <td>{{ $log->subject_type }} {{ $log->description }}</td>
                        <td class="small text-muted">
                            @foreach ($log->changes ?? [] as $field => $change)
                                <div>{{ $field }}: {{ is_scalar($change['from'] ?? null) ? $change['from'] : '' }} → {{ is_scalar($change['to'] ?? null) ? $change['to'] : '' }}</div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">{{ __('Nothing found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $logs->links('partials.pagination') }}
    </div>
</div>
@endsection
