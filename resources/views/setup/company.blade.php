@extends('layouts.app', ['title' => __('Company')])

@section('content')
<x-page-header :title="__('Company')" icon="building" />

<form method="POST" action="{{ route('company.update') }}" class="card">
    @csrf
    @method('PUT')
    <div class="card-body">
        <p class="text-muted">{{ __('These details are printed at the top of bills and statements.') }}</p>
        <div class="row g-3">
            @foreach ($fields as $key => $label)
                <div class="col-12 col-md-6">
                    <label class="form-label" for="f-{{ $key }}">{{ __($label) }}</label>
                    <input type="text" class="form-control @error($key) is-invalid @enderror" id="f-{{ $key }}" name="{{ $key }}" value="{{ old($key, setting($key)) }}">
                </div>
            @endforeach
        </div>
    </div>
    @can('setup.manage')
        <div class="card-footer d-flex justify-content-end">
            <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
        </div>
    @endcan
</form>
@endsection
