@extends('layouts.app', ['title' => __('Roles')])

@section('content')
<x-page-header :title="__('Roles')" icon="user-shield">
    @can('users.manage')
        <a class="btn btn-primary" href="{{ route('roles.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Add :name', ['name' => __('Role')]) }}</a>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Role') }}</th><th>{{ __('Users') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr>
                        <td>{{ $role->name }}</td>
                        <td>{{ $role->users_count }}</td>
                        <td class="text-end text-nowrap">
                            @if ($role->name === \App\Models\User::ADMIN_ROLE)
                                <span class="text-muted small">{{ __('May do everything') }}</span>
                            @else
                                @can('users.manage')
                                    <a class="btn btn-sm btn-light-secondary" href="{{ route('roles.edit', ['id' => $role->id]) }}">{{ __('Edit') }}</a>
                                    <form method="POST" action="{{ route('roles.destroy', ['id' => $role->id]) }}" class="d-inline" data-confirm="{{ __('Delete this?') }}" data-confirm-ok="{{ __('Delete') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light-danger">{{ __('Delete') }}</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
