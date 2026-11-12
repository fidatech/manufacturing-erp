@extends('layouts.app', ['title' => __('Balance sheet')])

@php
    $titles = ['asset' => __('What the company has'), 'liability' => __('What the company owes'), 'equity' => __('What belongs to the owners')];
@endphp

@section('content')
<x-page-header :title="__('Balance sheet')" icon="building-columns" :crumbs="[[__('Reports'), route('reports.index')], [__('Balance sheet')]]" />

<div class="card">
    <div class="card-body">
        @include('reports.filter', ['range' => false, 'heading' => __('Balance sheet')])
        <table class="table w-100 keep-table">
            <tbody>
                @foreach ($sections as $nature => $groups)
                    <tr class="table-light"><th colspan="2">{{ $titles[$nature] }}</th></tr>
                    @foreach ($groups as $group)
                        <tr class="fw-semibold"><td>{{ __($group['name']) }}</td><td class="text-end">{{ money($group['amount']) }}</td></tr>
                        @foreach ($group['members'] as $member)
                            <tr class="small text-muted"><td class="ps-4">{{ $member['name'] }}</td><td class="text-end">{{ money($member['amount']) }}</td></tr>
                        @endforeach
                    @endforeach
                    @if ($nature === 'equity')
                        <tr class="fw-semibold"><td>{{ __('Profit so far') }}</td><td class="text-end">{{ money($profit) }}</td></tr>
                    @endif
                    <tr class="fw-bold"><td>{{ __('Total') }}: {{ $titles[$nature] }}</td><td class="text-end">{{ money($totals[$nature], $base) }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <p class="mb-0 {{ $balanced ? 'text-success' : 'text-danger' }}">{{ $balanced ? __('The books balance: what the company has equals what it owes plus what belongs to the owners.') : __('The books do not balance. Please tell your system administrator.') }}</p>
    </div>
</div>
@endsection
