@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => __('Dead Cash')])

@php
    use App\Support\Num;
@endphp

@section('content')
<x-page-header :title="__('Dead Cash')" icon="ban" :subtitle="__('Customers who owe us and have not paid for a long time.')" />

@if (request()->boolean('print'))
    @section('doc')
        <h2>{{ __('Dead Cash') }}</h2>
        <div>{{ __('Not paid for :days days or more', ['days' => $days]) }} · {{ fmt_date(today_iso()) }}</div>
    @endsection
@endif

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="card stat-card mb-0"><div class="sc-top"><div><div class="sc-label">{{ __('Customers') }}</div><div class="sc-value">{{ $rows->count() }}</div></div><span class="sc-icon icon-soft danger"><x-icon name="users" /></span></div><div class="sc-foot">{{ __(':days days or more without paying', ['days' => $days]) }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="card stat-card mb-0"><div class="sc-top"><div><div class="sc-label">{{ __('They owe us (:base)', ['base' => $base]) }}</div><div class="sc-value text-danger">{{ money($totalBase) }}</div></div><span class="sc-icon icon-soft warning"><x-icon name="sack-dollar" /></span></div><div class="sc-foot">{{ __('At today\'s rates') }}</div></div></div>
    @foreach ($byCurrency->take(2) as $code => $amount)
        <div class="col-6 col-xl-3"><div class="card stat-card mb-0"><div class="sc-top"><div><div class="sc-label">{{ __('Owed in :code', ['code' => $code]) }}</div><div class="sc-value">{{ money($amount) }}</div></div><span class="sc-icon icon-soft primary"><x-icon name="coins" /></span></div><div class="sc-foot">{{ $code }}</div></div></div>
    @endforeach
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3 no-print">
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1">{{ __('Not paid for') }}</label>
                <select class="form-select" name="days">
                    @foreach ($dayOptions as $option)
                        <option value="{{ $option }}" @selected($days === $option)>{{ __(':days days or more', ['days' => $option]) }}</option>
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
                <label class="form-label small mb-1">{{ __('Category') }}</label>
                <select class="form-select" name="category">
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ __($category) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Name, code or phone') }}">
            </div>
            <div class="col-auto"><button class="btn btn-primary" type="submit">{{ __('Show') }}</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Phone') }}</th><th class="text-end">{{ __('Owes us') }}</th><th>{{ __('Last payment') }}</th><th>{{ __('Last sale') }}</th><th class="text-end">{{ __('Days without paying') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $account = $row['account'];
                            $tone = $row['days'] >= 365 ? 'danger' : ($row['days'] >= 180 ? 'warning' : 'secondary');
                        @endphp
                        <tr>
                            <td class="text-nowrap" dir="ltr">{{ $account->code }}</td>
                            <td><a href="{{ route('statement', ['id' => $account->id]) }}" class="fw-semibold">{{ $account->name }}</a>@if ($account->metaValue('category'))<div class="small text-muted">{{ __($account->metaValue('category')) }}</div>@endif</td>
                            <td class="text-nowrap" dir="ltr">{{ $account->phone }}</td>
                            <td class="text-end text-nowrap fw-bold text-danger">{{ money($row['owed'], $account->currency->code) }}</td>
                            <td class="text-nowrap">{{ $row['last_paid'] ? fmt_date($row['last_paid']) : __('Never paid') }}</td>
                            <td class="text-nowrap">{{ $row['last_sale'] ? fmt_date($row['last_sale']) : '—' }}</td>
                            <td class="text-end"><span class="badge-soft {{ $tone }}">{{ __(':days days', ['days' => $row['days']]) }}</span></td>
                            <td class="text-end text-nowrap no-print">
                                @can('money.manage')
                                    <a class="btn btn-sm btn-light-success" href="{{ route('money.create', ['kind' => 'in', 'group' => 'customer', 'account' => $account->id]) }}">{{ __('Receive money') }}</a>
                                    <button type="button" class="btn btn-sm btn-light-danger" data-bs-toggle="modal" data-bs-target="#writeoff-{{ $account->id }}">{{ __('Write off') }}</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No customer owes us for that long.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if ($entries->isNotEmpty())
    <div class="card no-print">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div><h5 class="card-title">{{ __('Written off') }}</h5><div class="small text-muted">{{ __('Debts taken out of the books as a loss.') }}</div></div>
            <span class="badge-soft danger fs-6">{{ money($total, $base) }}</span>
        </div>
        <div class="card-body">
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('No.') }}</th><th>{{ __('Details') }}</th><th>{{ __('Accounts') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr>
                            <td class="text-nowrap">{{ fmt_date($entry->date) }}</td>
                            <td class="text-nowrap">{{ $entry->number }}@if ($entry->isVoid()) <span class="badge-soft danger">{{ __('Cancelled') }}</span>@endif</td>
                            <td>{{ $entry->description }}</td>
                            <td>@include('partials.entry-lines')</td>
                            <td class="text-end">
                                @if (! $entry->isVoid())
                                    @can('money.manage')
                                        <form method="POST" action="{{ route('dead-cash.destroy', ['id' => $entry->id]) }}" class="d-inline" data-confirm="{{ __('Cancel this entry?') }}" data-confirm-text="{{ __('The debt goes back to the customer.') }}" data-confirm-ok="{{ __('Cancel it') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light-danger">{{ __('Cancel') }}</button>
                                        </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $entries->links('partials.pagination') }}
        </div>
    </div>
@endif

@can('money.manage')
    @foreach ($rows as $row)
        <div class="modal fade" id="writeoff-{{ $row['account']->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('dead-cash.store') }}" class="modal-content">
                    @csrf
                    <input type="hidden" name="account_id" value="{{ $row['account']->id }}">
                    <div class="modal-header"><h5 class="modal-title">{{ __('Write off :name', ['name' => $row['account']->name]) }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
                    <div class="modal-body">
                        <p class="text-muted">{{ __('Use this only when the money will never come back. It is taken off the customer\'s account and counted as a loss.') }}</p>
                        <div class="row g-3">
                            <div class="col-6"><label class="form-label">{{ __('Date') }}</label><x-date name="date" :value="today_iso()" required /></div>
                            <div class="col-6"><label class="form-label">{{ __('Amount') }} ({{ $row['account']->currency->code }})</label><input type="text" inputmode="decimal" dir="ltr" autocomplete="off" class="form-control" name="amount" value="{{ $row['owed'] }}" required></div>
                            <div class="col-12"><label class="form-label">{{ __('Reason') }}</label><input type="text" class="form-control" name="reason" maxlength="500" required placeholder="{{ __('e.g. the customer left the country') }}"></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-danger">{{ __('Write off') }}</button></div>
                </form>
            </div>
        </div>
    @endforeach
@endcan
@endsection
