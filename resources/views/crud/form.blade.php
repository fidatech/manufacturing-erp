@extends('layouts.app', ['title' => ($record ? __('Edit :name', ['name' => $singular]) : __('Add :name', ['name' => $singular]))])

@section('content')
<x-page-header :title="$record ? __('Edit :name', ['name' => $singular]) : __('Add :name', ['name' => $singular])" :icon="$icon" :crumbs="[[$title, route($route.'.index', $routeParams)], [$record ? __('Edit') : __('Add')]]" />

<form method="POST" action="{{ $action }}" class="card">
    @csrf
    @if ($record)
        @method('PUT')
    @endif
    <div class="card-body">
        <div class="row g-3">
            @foreach ($fields as $field)
                @include('crud.field', ['field' => $field])
            @endforeach
        </div>
    </div>
    <div class="card-footer d-flex gap-2 justify-content-end">
        <a class="btn btn-light-secondary" href="{{ route($route.'.index', $routeParams) }}">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" class="me-1" /> {{ __('Save') }}</button>
    </div>
</form>
@endsection
