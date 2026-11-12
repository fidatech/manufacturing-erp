@extends('layouts.app', ['title' => __('Statement').': '.$account->name])

@php
    use App\Support\Num;

    $code = $account->currency->code;
    $words = $account->balanceWords($closing);
    // green when they owe us (or we have it), red when we owe, blue for owners' capital
    $tone = fn ($balance) => 'is-'.$account->balanceTone($balance);
    $show = fn ($balance) => $money || $account->type->is_party ? money(Num::abs($balance)) : money($balance);
    $kindIcon = $money ? 'wallet' : ($account->type->is_party ? 'user' : 'folder');
    $periodText = fmt_date($from).' – '.fmt_date($to);
@endphp

@section('content')
<x-page-header :title="__('Account statement')" icon="file-invoice" :crumbs="[[__('Chart of Accounts'), auth()->user()->can('accounts.view') ? route('chart') : null], [$account->name]]">
    <button type="button" class="btn btn-primary" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
</x-page-header>

<div class="stmt">
    @include('statement.letterhead', ['heading' => __('Account statement'), 'period' => $periodText, 'note' => __('Amounts in :base', ['base' => $code])])

    {{-- Who this statement is about, and where the account stands today --}}
    <div class="card stmt-id">
        <div class="card-body">
            <div class="stmt-who">
                <span class="avatar avatar-lg avatar-soft-primary">{{ $account->initials() }}</span>
                <div class="stmt-who-text">
                    <h2>{{ $account->name }}</h2>
                    <div class="stmt-tags">
                        <span class="badge-soft primary"><x-icon :name="$kindIcon" class="me-1" />{{ __($account->type->name) }}</span>
                        <span class="badge-soft secondary">{{ $code }}</span>
                        @if ($account->code)
                            <span class="badge-soft secondary">{{ $account->code }}</span>
                        @endif
                        @unless ($account->is_active)
                            <span class="badge-soft danger">{{ __('Not active') }}</span>
                        @endunless
                    </div>
                    <ul class="stmt-contact">
                        @if ($account->company)
                            <li><x-icon name="building" /> {{ $account->company }}</li>
                        @endif
                        @if ($account->phone)
                            <li><x-icon name="phone" /> <span dir="ltr">{{ $account->phone }}</span></li>
                        @endif
                        @if ($account->address)
                            <li><x-icon name="location-dot" /> {{ $account->address }}</li>
                        @endif
                    </ul>
                </div>
            </div>
            <div class="stmt-now {{ $tone($closing) }}">
                <div class="stmt-now-label">{{ __('Balance on :date', ['date' => fmt_date($to)]) }}</div>
                <div class="stmt-now-value">{{ $show($closing) }} <small>{{ $code }}</small></div>
                @if ($words !== '')
                    <span class="stmt-now-words">{{ $words }}</span>
                @endif
            </div>
        </div>
    </div>

    @include('statement.period')

    {{-- The period in four numbers --}}
    <div class="row g-3 stmt-cards">
        <div class="col-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="sc-top">
                    <div><div class="sc-label">{{ __('Balance before') }}</div><div class="sc-value">{{ $show($opening) }}</div></div>
                    <span class="sc-icon icon-soft info"><x-icon name="hourglass-start" /></span>
                </div>
                <div class="sc-foot">{{ $account->balanceWords($opening) ?: fmt_date($from) }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="sc-top">
                    <div><div class="sc-label">{{ $columns[0] }}</div><div class="sc-value">{{ money($debit) }}</div></div>
                    <span class="sc-icon icon-soft success"><x-icon name="arrow-down" /></span>
                </div>
                <div class="sc-foot">{{ $code }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="sc-top">
                    <div><div class="sc-label">{{ $columns[1] }}</div><div class="sc-value">{{ money($credit) }}</div></div>
                    <span class="sc-icon icon-soft danger"><x-icon name="arrow-up" /></span>
                </div>
                <div class="sc-foot">{{ $code }}</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="sc-top">
                    <div><div class="sc-label">{{ __('Balance now') }}</div><div class="sc-value">{{ $show($closing) }}</div></div>
                    <span class="sc-icon icon-soft primary"><x-icon name="scale-balanced" /></span>
                </div>
                <div class="sc-foot">{{ $words ?: fmt_date($to) }}</div>
            </div>
        </div>
    </div>

    {{-- Every movement, oldest first, with the balance after it --}}
    <div class="card stmt-ledger">
        <div class="stmt-ledger-head">
            <h3>{{ __('Movements') }}</h3>
            <span class="badge-soft secondary">{{ count($rows) }}</span>
            <span class="stmt-ledger-period">{{ $periodText }}</span>
        </div>
        @if ($rows)
            <div class="table-responsive">
                <table class="table stmt-table keep-table w-100">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Details') }}</th>
                            <th class="text-end">{{ $columns[0] }}</th>
                            <th class="text-end">{{ $columns[1] }}</th>
                            <th class="text-end">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="stmt-row-open">
                            <td class="text-nowrap">{{ fmt_date($from) }}</td>
                            <td colspan="3">{{ __('Balance before :date', ['date' => fmt_date($from)]) }}</td>
                            <td data-label="{{ __('Balance') }}" class="text-end text-nowrap stmt-bal {{ $tone($opening) }}">{{ $show($opening) }}</td>
                        </tr>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="text-nowrap">{{ fmt_date($row['line']->date) }}</td>
                                <td>
                                    <div class="stmt-what">{{ $row['line']->memo ?: ($row['line']->entry->description ?: $row['kind']['label']) }}</div>
                                    <div class="stmt-ref">
                                        <span class="badge-soft {{ $row['kind']['color'] }}">{{ $row['kind']['label'] }}</span>
                                        @if ($row['url'])
                                            <a href="{{ $row['url'] }}">{{ $row['line']->entry->number }}</a>
                                        @else
                                            <span>{{ $row['line']->entry->number }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-end text-nowrap stmt-in">{{ Num::isZero($row['line']->debit) ? '' : money($row['line']->debit) }}</td>
                                <td class="text-end text-nowrap stmt-out">{{ Num::isZero($row['line']->credit) ? '' : money($row['line']->credit) }}</td>
                                <td data-label="{{ __('Balance') }}" class="text-end text-nowrap stmt-bal {{ $tone($row['balance']) }}">{{ $show($row['balance']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2">{{ __('Total') }}</td>
                            <td data-label="{{ $columns[0] }}" class="text-end text-nowrap">{{ money($debit) }}</td>
                            <td data-label="{{ $columns[1] }}" class="text-end text-nowrap">{{ money($credit) }}</td>
                            <td data-label="{{ __('Balance') }}" class="text-end text-nowrap stmt-bal {{ $tone($closing) }}">{{ $show($closing) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @if ($money || $account->type->is_party)
                <div class="stmt-legend">
                    <span class="stmt-bal {{ $tone('1') }}">●</span> {{ $account->balanceWords('1') ?: __('we have') }}
                    <span class="stmt-bal {{ $tone('-1') }} ms-3">●</span> {{ $account->balanceWords('-1') }}
                </div>
            @endif
        @else
            <div class="empty-state">
                <div class="es-icon"><x-icon name="inbox" /></div>
                <h3>{{ __('Nothing happened in these dates') }}</h3>
                <p>{{ __('Choose other dates above, or press "All time" to see everything.') }}</p>
            </div>
        @endif
        <div class="stmt-close">
            <span>{{ __('Balance on :date', ['date' => fmt_date($to)]) }}</span>
            {{ $account->balanceHtml($closing) }}
        </div>
    </div>

    @include('statement.signatures', ['third' => $account->type->is_party ? __('Confirmed by :name', ['name' => $account->name]) : null])
</div>
@endsection
