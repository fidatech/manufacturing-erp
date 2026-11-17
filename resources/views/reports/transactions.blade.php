@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => __('Enterprise transactions')])

@php
    use App\Support\EntryType;
    use App\Support\Num;
@endphp

@section('content')
<x-page-header :title="__('Enterprise transactions')" icon="list-ul" :crumbs="[[__('Reports'), route('reports.index')], [__('Enterprise transactions')]]" :subtitle="__('Every entry of every account, by module. Amounts in the account\'s own currency and in :base.', ['base' => $base])" />

@if (request()->boolean('print'))
    @section('doc')
        <h2>{{ __('Enterprise transactions') }}</h2>
        <div>{{ fmt_date($from) }} – {{ fmt_date($to) }}{{ ! empty($filters['module']) ? ' · '.$moduleNames[$filters['module']] : '' }}</div>
        <div>{{ __('Amounts in :base', ['base' => $base]) }}</div>
    @endsection
@endif

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end no-print">
            @include('partials.date-filter')
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1">{{ __('Module') }}</label>
                <select class="form-select" name="module">
                    <option value="">{{ __('All modules') }}</option>
                    @foreach ($moduleNames as $key => $name)
                        <option value="{{ $key }}" @selected(($filters['module'] ?? '') === $key)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small mb-1">{{ __('Account') }}</label>
                <select class="form-select" name="account" data-search>
                    <option value="">{{ __('All accounts') }}</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) ($filters['account'] ?? '') === (string) $account->id)>{{ $account->code ? $account->code.' · ' : '' }}{{ $account->name }} ({{ $account->currency->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1">{{ __('Currency') }}</label>
                <select class="form-select" name="currency">
                    <option value="">{{ __('All currencies') }}</option>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->id }}" @selected((string) ($filters['currency'] ?? '') === (string) $currency->id)>{{ $currency->code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Number or details') }}">
            </div>
            <div class="col-auto"><button class="btn btn-primary" type="submit">{{ __('Show') }}</button></div>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="card-title">{{ __('By module (:base)', ['base' => $base]) }}</h5></div>
    <div class="card-body">
        <table class="table w-100 keep-table">
            <thead><tr><th>{{ __('Module') }}</th><th class="text-end">{{ __('Entries') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th></tr></thead>
            <tbody>
                @forelse ($modules as $key => $module)
                    <tr>
                        <td><a href="{{ request()->fullUrlWithQuery(['module' => $key, 'page' => null]) }}">{{ $module['name'] }}</a></td>
                        <td class="text-end">{{ $module['entries'] }}</td>
                        <td class="text-end">{{ money($module['debit']) }}</td>
                        <td class="text-end">{{ money($module['credit']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">{{ __('Nothing in these days.') }}</td></tr>
                @endforelse
            </tbody>
            @if ($modules->isNotEmpty())
                <tfoot><tr class="fw-bold"><td>{{ __('Total') }}</td><td class="text-end">{{ $totals['entries'] }}</td><td class="text-end">{{ money($totals['debit'], $base) }}</td><td class="text-end">{{ money($totals['credit'], $base) }}</td></tr></tfoot>
            @endif
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title">{{ __('Transactions') }}</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('No.') }}</th><th>{{ __('Module') }}</th><th>{{ __('Code') }}</th><th>{{ __('Account') }}</th><th>{{ __('Details') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th class="text-end">{{ __('Debit (:base)', ['base' => $base]) }}</th><th class="text-end">{{ __('Credit (:base)', ['base' => $base]) }}</th></tr></thead>
                <tbody>
                    @forelse ($lines as $line)
                        @php $meta = EntryType::meta($line->entry_type); @endphp
                        <tr>
                            <td class="text-nowrap">{{ fmt_date($line->date) }}</td>
                            <td class="text-nowrap">{{ $line->entry_number }}</td>
                            <td class="text-nowrap">{{ $moduleNames[\App\Http\Controllers\Reports\ReportController::moduleOf($line->entry_type)] }} <span class="badge-soft {{ $meta['color'] }}">{{ $meta['label'] }}</span></td>
                            <td class="text-nowrap" dir="ltr">{{ $line->account->code }}</td>
                            <td><a href="{{ route('statement', ['id' => $line->account_id]) }}">{{ $line->account->name }}</a></td>
                            <td>{{ $line->memo ?: $line->entry_description }}@if ($line->entry_reference) <span class="text-muted small">· {{ $line->entry_reference }}</span>@endif</td>
                            <td class="text-end text-nowrap">{{ Num::isPositive($line->debit) ? money($line->debit, $line->account->currency->code) : '' }}</td>
                            <td class="text-end text-nowrap">{{ Num::isPositive($line->credit) ? money($line->credit, $line->account->currency->code) : '' }}</td>
                            <td class="text-end text-nowrap">{{ Num::isPositive($line->base_debit) ? money($line->base_debit) : '' }}</td>
                            <td class="text-end text-nowrap">{{ Num::isPositive($line->base_credit) ? money($line->base_credit) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">{{ __('Nothing in these days.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $lines->links('partials.pagination') }}
    </div>
</div>
@endsection
