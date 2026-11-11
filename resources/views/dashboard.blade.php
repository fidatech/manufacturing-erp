@extends('layouts.app', ['title' => __('app.dashboard.title')])

@section('content')
<x-page-header :title="__('app.dashboard.title')" icon="gauge-high" />

<div class="card">
    <div class="card-body">
        <div class="empty-state">
            <div class="es-icon"><x-icon name="industry" /></div>
            <h3>{{ __('app.dashboard.empty_title', ['name' => auth()->user()->name]) }}</h3>
            <p>{{ __('app.dashboard.empty_text') }}</p>
        </div>
    </div>
</div>
@endsection
