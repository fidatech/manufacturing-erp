@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => __('Expenses')])

@php
    use App\Support\Num;
@endphp

@section('content')
<x-page-header :title="__('Expenses')" icon="receipt" :subtitle="__('Money spent on rent, fuel, salaries and other costs.')">
    @can('money.manage')
        <a class="btn btn-primary" href="{{ route('money.create', ['kind' => 'expense']) }}"><x-icon name="plus" class="me-1" /> {{ __('New expense') }}</a>
    @endcan
</x-page-header>

@if (request()->boolean('print'))
    @section('doc')
        <h2>{{ __('Expenses') }}</h2>
        <div>{{ fmt_date($from) }} – {{ fmt_date($to) }}</div>
        <div>{{ __('Amounts in :base', ['base' => $base]) }}</div>
    @endsection
@endif

<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            @include('partials.date-filter', ['from' => $from, 'to' => $to])
            <div class="col-12 col-md-3">
                <label class="form-label small mb-1">{{ __('Expense category') }}</label>
                <select class="form-select" name="category" data-search>
                    <option value="">{{ __('All categories') }}</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['category'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1">{{ __('Paid in') }}</label>
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

<div class="row g-4 mb-4">
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="small text-muted">{{ __('Spent from :from to :to', ['from' => fmt_date($from), 'to' => fmt_date($to)]) }}</div>
                <div class="fs-2 fw-bold text-danger" dir="ltr">{{ money($total, $base) }}</div>
                <div class="small text-muted">{{ trans_choice(':count expense|:count expenses', $byCategory->sum('entries'), ['count' => $byCategory->sum('entries')]) }}</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-header"><h5 class="card-title">{{ __('By category (:base)', ['base' => $base]) }}</h5></div>
            <div class="card-body">
                @forelse ($byCategory as $row)
                    @php $share = Num::isPositive($total) ? round((float) $row->total / (float) $total * 100) : 0; @endphp
                    <div class="exp-cat">
                        <div class="d-flex justify-content-between gap-2">
                            <a href="{{ request()->fullUrlWithQuery(['category' => $row->id, 'page' => null]) }}">{{ $row->name }}</a>
                            <span class="fw-semibold text-nowrap" dir="ltr">{{ money($row->total) }} <span class="text-muted small">· {{ $share }}%</span></span>
                        </div>
                        <div class="exp-bar"><span style="width: {{ max($share, 1) }}%"></span></div>
                    </div>
                @empty
                    <div class="text-muted text-center py-3">{{ __('No expenses in these days.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title">{{ __('Expenses') }}</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover w-100">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('No.') }}</th><th>{{ __('Expense category') }}</th><th>{{ __('Paid from') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Amount (:base)', ['base' => $base]) }}</th><th>{{ __('Note') }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                    @forelse ($lines as $line)
                        @php
                            $void = $line->entry_voided_at || $line->is_void;
                            $paid = $line->entry->lines->first(fn ($l) => Num::isPositive($l->credit));
                        @endphp
                        <tr class="{{ $void ? 'text-decoration-line-through text-muted' : '' }}">
                            <td class="text-nowrap">{{ fmt_date($line->date) }}</td>
                            <td class="text-nowrap">{{ $line->entry_number }}@if ($void) <span class="badge-soft danger">{{ __('Cancelled') }}</span>@endif</td>
                            <td><span class="badge-soft secondary">{{ $line->account->name }}</span></td>
                            <td>{{ $paid?->account->name }}</td>
                            <td class="text-end text-nowrap fw-semibold">{{ $paid ? money($paid->credit, $paid->account->currency->code) : '' }}</td>
                            <td class="text-end text-nowrap">{{ money($line->base_debit) }}</td>
                            <td>{{ $line->memo ?: $line->entry_description }}</td>
                            <td class="text-end text-nowrap no-print">
                                <a class="btn btn-sm btn-light-secondary" href="{{ route('money.receipt', ['id' => $line->journal_entry_id]) }}" target="_blank" rel="noopener" title="{{ __('Print') }}"><x-icon name="print" /></a>
                                @if (! $void)
                                    @can('money.manage')
                                        <form method="POST" action="{{ route('money.destroy', ['id' => $line->journal_entry_id]) }}" class="d-inline" data-confirm="{{ __('Cancel this entry?') }}" data-confirm-text="{{ __('It stays in the day book but no longer counts.') }}" data-confirm-ok="{{ __('Cancel it') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light-danger">{{ __('Cancel') }}</button>
                                        </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No expenses in these days.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $lines->links('partials.pagination') }}
    </div>
</div>
@endsection
