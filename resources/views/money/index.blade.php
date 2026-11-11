@extends('layouts.app', ['title' => __('Money')])

@section('content')
<x-page-header :title="__('Money')" icon="money-bill-wave" :subtitle="__('Money received, money paid, expenses and transfers.')">
    @can('money.manage')
        <a class="btn btn-success" href="{{ route('money.create', ['kind' => 'in']) }}"><x-icon name="arrow-down" class="me-1" /> {{ __('Money in') }}</a>
        <a class="btn btn-danger" href="{{ route('money.create', ['kind' => 'out']) }}"><x-icon name="arrow-up" class="me-1" /> {{ __('Money out') }}</a>
        <a class="btn btn-warning" href="{{ route('money.create', ['kind' => 'expense']) }}"><x-icon name="receipt" class="me-1" /> {{ __('Expense') }}</a>
        <a class="btn btn-outline-soft" href="{{ route('money.create', ['kind' => 'transfer']) }}"><x-icon name="arrow-right-arrow-left" class="me-1" /> {{ __('Transfer / exchange') }}</a>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3">
            @include('partials.date-filter', ['from' => $filters['from'] ?? null, 'to' => $filters['to'] ?? null])
            <div class="col-6 col-md-auto">
                <label class="form-label small mb-1">{{ __('Kind') }}</label>
                <select class="form-select" name="type">
                    <option value="">{{ __('All') }}</option>
                    @foreach ($types as $key => $name)
                        <option value="{{ $key }}" @selected(($filters['type'] ?? '') === $key)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('No.') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Details') }}</th><th>{{ __('Accounts') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="text-nowrap">{{ fmt_date($entry->date) }}</td>
                        <td class="text-nowrap">{{ $entry->number }}</td>
                        <td>{{ $types[$entry->type] ?? $entry->type }}@if ($entry->isVoid()) <span class="badge-soft danger">{{ __('Cancelled') }}</span>@endif</td>
                        <td>{{ $entry->description }}</td>
                        <td>@include('partials.entry-lines')</td>
                        <td class="text-end">
                            @if (! $entry->isVoid() && ! $entry->source_type)
                                @can('money.manage')
                                    <form method="POST" action="{{ route('money.destroy', ['id' => $entry->id]) }}" class="d-inline" data-confirm="{{ __('Cancel this entry?') }}" data-confirm-text="{{ __('It stays in the day book but no longer counts.') }}" data-confirm-ok="{{ __('Cancel it') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-light-danger">{{ __('Cancel') }}</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('Nothing here yet') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $entries->links('partials.pagination') }}
    </div>
</div>
@endsection
