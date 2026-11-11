@extends('layouts.app', ['title' => __('Lab test')])

@php
    // start with the company's own list of things to check, or three empty rows
    $startLines = old('lines') ?? ($parameters ? array_map(fn ($name) => ['parameter' => $name], array_values($parameters)) : [[], [], []]);
@endphp

@section('content')
<x-page-header :title="$subject === 'raw' ? __('Test a raw material') : __('Test a batch')" icon="vial" :crumbs="[[__('Lab Tests'), route('lab.index')], [__('Lab test')]]" :subtitle="__('Write what was checked and what was found. Untick \'Good\' on anything that is not right: the test then fails.')" />

<form method="POST" action="{{ route('lab.store') }}">
    @csrf
    <input type="hidden" name="subject" value="{{ $subject }}">
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <label class="form-label" for="f-date">{{ __('Date') }} <span class="text-danger">*</span></label>
                    <x-date name="date" :value="old('date', today_iso())" id="f-date" required />
                </div>
                @if ($subject === 'product')
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="f-batch">{{ __('Batch') }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="f-batch" name="production_batch_id" required data-search>
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($batches as $option)
                                <option value="{{ $option->id }}" @selected((string) old('production_batch_id', $batch?->id) === (string) $option->id)>{{ $option->batch_no }} · {{ $option->item->label() }} · {{ fmt_date($option->date) }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="f-item">{{ __('Raw material') }} <span class="text-danger">*</span></label>
                        <select class="form-select" id="f-item" name="item_id" required data-search>
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($items as $item)
                                <option value="{{ $item->id }}" @selected((string) old('item_id') === (string) $item->id)>{{ $item->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="f-batchno">{{ __('Batch number') }}</label>
                        <input type="text" class="form-control" id="f-batchno" name="batch_no" value="{{ old('batch_no') }}" maxlength="60">
                    </div>
                @endif
                <div class="col-6 col-md-3">
                    <label class="form-label" for="f-by">{{ __('Tested by') }}</label>
                    <input type="text" class="form-control" id="f-by" name="tested_by" value="{{ old('tested_by', auth()->user()->name) }}" maxlength="150">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table w-100 keep-table" data-rows>
                    <thead><tr><th>{{ __('What was checked') }}</th><th>{{ __('Should be') }}</th><th>{{ __('Found') }}</th><th class="text-center">{{ __('Good') }}</th><th></th></tr></thead>
                    <tbody data-rows-body>
                        @foreach ($startLines as $i => $line)
                            @include('lab.row', ['i' => $i, 'line' => $line])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <template data-row-template>@include('lab.row', ['i' => '__i__', 'line' => []])</template>
            <datalist id="qc-parameters">
                @foreach ($parameters as $name)
                    <option value="{{ $name }}"></option>
                @endforeach
            </datalist>
            <button type="button" class="btn btn-sm btn-outline-soft" data-add-row><x-icon name="plus" /> {{ __('Add line') }}</button>
            <div class="form-text">{{ __('The usual things to check are kept under Laboratory > Test Parameters.') }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <label class="form-label" for="f-notes">{{ __('Notes') }}</label>
            <textarea class="form-control" id="f-notes" name="notes" rows="2">{{ old('notes') }}</textarea>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
            <a class="btn btn-light-secondary" href="{{ route('lab.index') }}">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save test') }}</button>
        </div>
    </div>
</form>
@endsection
