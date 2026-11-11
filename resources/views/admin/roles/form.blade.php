@extends('layouts.app', ['title' => __('Role')])

@section('content')
<x-page-header :title="$role ? __('Edit :name', ['name' => __('Role')]) : __('Add :name', ['name' => __('Role')])" icon="user-shield" :crumbs="[[__('Roles'), route('roles.index')], [__('Role')]]" />

<form method="POST" action="{{ $role ? route('roles.update', ['id' => $role->id]) : route('roles.store') }}" class="card">
    @csrf
    @if ($role)
        @method('PUT')
    @endif
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="f-name">{{ __('Role name') }} <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="f-name" name="name" value="{{ old('name', $role?->name) }}" required>
                @error('name')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
                <div class="form-text">{{ __('For example: Accountant, Storekeeper, Salesman') }}</div>
            </div>
        </div>
        <table class="table w-100 keep-table">
            <thead><tr><th>{{ __('What this role may do') }}</th><th class="text-center">{{ __('See') }}</th><th class="text-center">{{ __('Add and change') }}</th></tr></thead>
            <tbody>
                @foreach ($modules as $module => $label)
                    <tr>
                        <td>{{ __($label) }}</td>
                        @foreach (['view', 'manage'] as $level)
                            <td class="text-center">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $module }}.{{ $level }}" aria-label="{{ __($label) }} {{ $level }}" @checked(in_array("$module.$level", old('permissions', $granted), true))>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route('roles.index') }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>
@endsection
