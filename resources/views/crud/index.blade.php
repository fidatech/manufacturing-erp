@extends('layouts.app', ['title' => $title])

@section('content')
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
            <div class="col">
                <input type="search" class="form-control" name="q" value="{{ $term }}" placeholder="{{ __('Search…') }}" aria-label="{{ __('Search') }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-light-secondary" type="submit"><x-icon name="magnifying-glass" /></button>
            </div>
        </form>

        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="es-icon"><x-icon :name="$icon" /></div>
                <h3>{{ $term !== '' ? __('Nothing found') : __('Nothing here yet') }}</h3>
                @if ($canManage && $term === '')
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
                        <th class="text-end">{{ __('Actions') }}</th>
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
