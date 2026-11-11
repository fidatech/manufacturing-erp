@extends('layouts.app', ['title' => __('Company')])

@section('content')
<x-page-header :title="__('Company')" icon="building" />

<form method="POST" action="{{ route('company.update') }}" class="card" enctype="multipart/form-data">
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
            <div class="col-12 col-md-6">
                <label class="form-label" for="f-logo">{{ __('Logo') }}</label>
                <input type="file" class="form-control @error('logo') is-invalid @enderror" id="f-logo" name="logo" accept="image/png,image/jpeg,image/webp">
                @error('logo')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <div class="form-text">{{ __('PNG, JPG or WebP, up to 300 KB. It is shown in the menu and printed on bills.') }}</div>
            </div>
            @if ($hasLogo)
                <div class="col-12 col-md-6">
                    <img src="{{ \App\Http\Controllers\Setup\CompanyController::logoUrl() }}" alt="{{ __('Logo') }}" class="erp-logo-preview">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="f-remove-logo" name="remove_logo" value="1">
                        <label class="form-check-label" for="f-remove-logo">{{ __('Remove the logo') }}</label>
                    </div>
                </div>
            @endif
        </div>
    </div>
    @can('setup.manage')
        <div class="card-footer d-flex justify-content-end">
            <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
        </div>
    @endcan
</form>
@endsection
