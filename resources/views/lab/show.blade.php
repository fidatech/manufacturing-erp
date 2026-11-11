@extends('layouts.app', ['title' => __('Lab test').' '.$test->number])

@section('content')
<x-page-header :title="__('Lab test').' '.$test->number" icon="vial" :crumbs="[[__('Lab Tests'), route('lab.index')], [$test->number]]">
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
    @can('lab.manage')
        <form method="POST" action="{{ route('lab.destroy', ['id' => $test->id]) }}" class="d-inline" data-confirm="{{ __('Delete this?') }}" data-confirm-ok="{{ __('Delete') }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-light-danger">{{ __('Delete') }}</button>
        </form>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Date') }}</div><strong>{{ fmt_date($test->date) }}</strong></div>
            <div class="col-6 col-md-4"><div class="small text-muted">{{ $test->subject === 'raw' ? __('Raw material') : __('Product') }}</div><strong>{{ $test->item->label() }}</strong></div>
            <div class="col-6 col-md-2"><div class="small text-muted">{{ __('Batch') }}</div><strong>@if ($test->batch)<a href="{{ route('production.show', ['id' => $test->batch->id]) }}">{{ $test->batch_no }}</a>@else{{ $test->batch_no ?: '-' }}@endif</strong></div>
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Result') }}</div><span class="badge-soft {{ $test->status === 'passed' ? 'success' : 'danger' }}">{{ $test->status === 'passed' ? __('Passed') : __('Failed') }}</span></div>
        </div>
        <table class="table w-100 keep-table">
            <thead><tr><th>#</th><th>{{ __('What was checked') }}</th><th>{{ __('Should be') }}</th><th>{{ __('Found') }}</th><th class="text-center">{{ __('Good') }}</th></tr></thead>
            <tbody>
                @foreach ($test->results as $row)
                    <tr>
                        <td>{{ $loop->iteration }}</td><td>{{ $row['parameter'] }}</td><td>{{ $row['standard'] }}</td><td>{{ $row['result'] }}</td>
                        <td class="text-center {{ $row['ok'] ? 'text-success' : 'text-danger fw-bold' }}">{{ $row['ok'] ? __('Yes') : __('No') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="mb-0"><span class="text-muted">{{ __('Tested by') }}:</span> {{ $test->tested_by }}@if ($test->notes) · <span class="text-muted">{{ __('Notes') }}:</span> {{ $test->notes }}@endif</p>
    </div>
</div>
@endsection
