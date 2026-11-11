@extends('layouts.app', ['title' => __('Chart of Accounts')])

@section('content')
<x-page-header :title="__('Chart of Accounts')" icon="sitemap" :subtitle="__('Every account of the company in one place. Balances are shown in :base.', ['base' => $base])" />

@foreach ($groups as $nature => $types)
    <div class="card mb-4">
        <div class="card-header"><h5 class="card-title">{{ $natures[$nature] }}</h5></div>
        <div class="card-body">
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Kind') }}</th><th class="text-end">{{ __('Balance') }}</th><th class="text-end">{{ __('In :base', ['base' => $base]) }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @php
                        $total = '0';
                    @endphp
                    @foreach ($types as $type)
                        @foreach ($type->accounts as $account)
                            @php
                                $own = $balances[$account->id]['own'] ?? '0';
                                $inBase = $balances[$account->id]['base'] ?? '0';
                                // liabilities, owners' money and income grow on the credit side: show them as positive
                                $shown = in_array($nature, ['liability', 'equity', 'income'], true) ? \App\Support\Num::neg($inBase) : $inBase;
                                $total = \App\Support\Num::add($total, $shown);
                            @endphp
                            <tr>
                                <td>{{ $account->name }}@unless ($account->is_active) <span class="badge-soft warning">{{ __('Off') }}</span>@endunless</td>
                                <td>{{ __($type->name) }}</td>
                                <td class="text-end text-nowrap">{{ $account->describeBalance($own) }}</td>
                                <td class="text-end text-nowrap">{{ money($shown) }}</td>
                                <td class="text-end"><a class="btn btn-sm btn-light-secondary" href="{{ route('statement', ['id' => $account->id]) }}">{{ __('Statement') }}</a></td>
                            </tr>
                        @endforeach
                    @endforeach
                    <tr class="fw-bold"><td colspan="3">{{ __('Total') }}</td><td class="text-end text-nowrap">{{ money($total, $base) }}</td><td></td></tr>
                </tbody>
            </table>
            @can('accounts.manage')
                <div class="d-flex flex-wrap gap-2 mt-2">
                    @foreach ($types->where('is_system', false) as $type)
                        <a class="btn btn-sm btn-outline-soft" href="{{ route('accounts.create', ['type' => $type->key]) }}"><x-icon name="plus" /> {{ __($type->name) }}</a>
                    @endforeach
                </div>
            @endcan
        </div>
    </div>
@endforeach
@endsection
