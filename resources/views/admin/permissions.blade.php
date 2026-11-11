@extends('layouts.app', ['title' => __('Permissions')])

@section('content')
<x-page-header :title="__('Permissions')" icon="key" :subtitle="__('What every role may do. To change it, open Roles and edit the role.')">
    @can('users.manage')
        <a class="btn btn-primary" href="{{ route('roles.index') }}">{{ __('Roles') }}</a>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover w-100 keep-table">
                <thead><tr><th>{{ __('What may be done') }}</th>@foreach ($roles as $role)<th class="text-center">{{ $role->name }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($modules as $module => $label)
                        <tr>
                            <td>{{ __($label) }}</td>
                            @foreach ($roles as $role)
                                @php
                                    $all = $role->name === \App\Models\User::ADMIN_ROLE;
                                    $manage = $all || in_array("$module.manage", $granted[$role->id], true);
                                    $see = $manage || in_array("$module.view", $granted[$role->id], true);
                                @endphp
                                <td class="text-center">
                                    @if ($manage)
                                        <span class="badge-soft success">{{ __('See and change') }}</span>
                                    @elseif ($see)
                                        <span class="badge-soft info">{{ __('See only') }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
