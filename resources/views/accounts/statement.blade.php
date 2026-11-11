@extends('layouts.app', ['title' => __('Statement').': '.$account->name])

@section('content')
<x-page-header :title="$account->name" icon="file-invoice" :subtitle="__($account->type->name).' · '.$account->currency->code.($account->phone ? ' · '.$account->phone : '')">
    <button type="button" class="btn btn-outline-soft" onclick="window.print()"><x-icon name="print" class="me-1" /> {{ __('Print') }}</button>
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3 no-print">
            @include('partials.date-filter')
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        <p class="mb-2"><strong>{{ __('Statement') }}</strong> · {{ fmt_date($from) }} – {{ fmt_date($to) }}</p>
        <table class="table table-hover w-100 keep-table">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th><th>{{ __('No.') }}</th><th>{{ __('Details') }}</th>
                    <th class="text-end">{{ $money ? __('In') : __('Debit') }}</th>
                    <th class="text-end">{{ $money ? __('Out') : __('Credit') }}</th>
                    <th class="text-end">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr class="text-muted"><td colspan="5">{{ __('Balance before :date', ['date' => fmt_date($from)]) }}</td><td class="text-end text-nowrap">{{ money($opening) }}</td></tr>
                @foreach ($rows as $row)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($row['line']->date) }}</td>
                        <td class="text-nowrap">{{ $row['line']->entry->number }}</td>
                        <td>{{ $row['line']->memo ?: $row['line']->entry->description }}</td>
                        <td class="text-end text-nowrap">{{ \App\Support\Num::isZero($row['line']->debit) ? '' : money($row['line']->debit) }}</td>
                        <td class="text-end text-nowrap">{{ \App\Support\Num::isZero($row['line']->credit) ? '' : money($row['line']->credit) }}</td>
                        <td class="text-end text-nowrap">{{ money($row['balance']) }}</td>
                    </tr>
                @endforeach
                <tr class="fw-bold">
                    <td colspan="3">{{ __('Total') }}</td>
                    <td class="text-end text-nowrap">{{ money($debit) }}</td>
                    <td class="text-end text-nowrap">{{ money($credit) }}</td>
                    <td class="text-end text-nowrap">{{ money($closing) }}</td>
                </tr>
            </tbody>
        </table>
        <p class="fs-5 mb-0">{{ __('Balance on :date', ['date' => fmt_date($to)]) }}: <strong>{{ $account->describeBalance($closing) }}</strong></p>
    </div>
</div>
@endsection
