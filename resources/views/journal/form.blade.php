@extends('layouts.app', ['title' => __('Journal entry')])

@section('content')
<x-page-header :title="__('Journal entry')" icon="book" :crumbs="[[__('Day Book'), route('journal.index')], [__('Journal entry')]]" :subtitle="__('For accountants. Everyday work is easier with Money in, Money out and Expense.')" />

<form method="POST" action="{{ route('journal.store') }}" class="card">
    @csrf
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="f-date">{{ __('Date') }} <span class="text-danger">*</span></label>
                <x-date name="date" :value="old('date', today_iso())" id="f-date" required />
            </div>
            <div class="col-12 col-md-8">
                <label class="form-label" for="f-description">{{ __('Details') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('description') is-invalid @enderror" id="f-description" name="description" value="{{ old('description') }}" required maxlength="500">
            </div>
        </div>

        <table class="table w-100 keep-table" data-rows>
            <thead><tr><th>{{ __('Account') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th></th></tr></thead>
            <tbody data-rows-body>
                @foreach (old('lines', [[], []]) as $i => $line)
                    @include('journal.row', ['i' => $i, 'line' => $line])
                @endforeach
            </tbody>
        </table>
        <template data-row-template>@include('journal.row', ['i' => '__i__', 'line' => []])</template>
        <button type="button" class="btn btn-sm btn-outline-soft" data-add-row><x-icon name="plus" /> {{ __('Add line') }}</button>
        <div class="form-text mt-2">{{ __('Amounts are in the currency of each account. A small exchange difference is balanced by the system.') }}</div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('journal.index') }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>
@endsection
