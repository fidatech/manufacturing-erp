@extends('layouts.app', ['title' => $title])

@section('content')
@php
    $filtered = collect($filters ?? [])->contains(fn ($filter) => (string) $filter['value'] !== '');
@endphp
<x-page-header :title="$title" :icon="$icon">
    @isset($headerLinks)
        @foreach ($headerLinks as $link)
            <a class="btn btn-outline-soft" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
        @endforeach
    @endisset
    @if ($canManage)
        <a class="btn btn-primary" href="{{ route($route.'.create', $routeParams) }}"><x-icon name="plus" class="me-1" /> {{ __('Add :name', ['name' => $singular]) }}</a>
    @endif
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-12 col-md">
                <input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search…') }}" aria-label="{{ __('Search') }}">
            </div>
            @foreach ($filters ?? [] as $filter)
                <div class="col col-md-auto">
                    <select class="form-select" name="{{ $filter['name'] }}" aria-label="{{ $filter['label'] }}" onchange="this.form.submit()">
                        <option value="">{{ $filter['label'] }}</option>
                        @foreach ($filter['options'] as $value => $text)
                            <option value="{{ $value }}" @selected((string) $filter['value'] === (string) $value)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-auto">
                <button class="btn btn-light-secondary" type="submit"><x-icon name="magnifying-glass" /></button>
            </div>
        </form>

        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon :name="$icon" /></div>
                <h3>{{ $term !== '' || $filtered ? __('Nothing found') : __('Nothing here yet') }}</h3>
                @if ($canManage && $term === '' && ! $filtered)
                    <p>{{ __('Press "Add :name" to create the first one.', ['name' => $singular]) }}</p>
                @endif
            </div>
        @else
            <table class="table table-hover w-100">
                <thead>
                    <tr>
                        @foreach (array_keys($columns) as $label)
                            <th>{{ __($label) }}</th>
                        @endforeach
                        <th class="text-end no-print">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($columns as $column)
                                <td>{{ $column($row) }}</td>
                            @endforeach
                            <td class="text-end text-nowrap">
                                @foreach ($links($row) as $link)
                                    <a class="btn btn-sm btn-light-secondary" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                                @endforeach
                                @if ($canManage && $editable($row))
                                    <a class="btn btn-sm btn-light-secondary" href="{{ route($route.'.edit', $routeParams + ['id' => $row->getKey()]) }}">{{ __('Edit') }}</a>
                                    <form method="POST" action="{{ route($route.'.destroy', $routeParams + ['id' => $row->getKey()]) }}" class="d-inline" data-confirm="{{ __('Delete this?') }}" data-confirm-ok="{{ __('Delete') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light-danger">{{ __('Delete') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $rows->links('partials.pagination') }}
        @endif
    </div>
</div>
@endsection
