@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => __('Day Book')])

@section('content')
<x-page-header :title="__('Day Book (Roznamcha)')" icon="book" :subtitle="__('Everything that was recorded, day by day.')">
    <a class="btn btn-outline-soft" href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank" rel="noopener"><x-icon name="print" class="me-1" /> {{ __('Print') }}</a>
    @can('journal.manage')
        <a class="btn btn-primary" href="{{ route('journal.create') }}"><x-icon name="plus" class="me-1" /> {{ __('Journal entry') }}</a>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3 no-print">
            @include('partials.date-filter')
            <div class="col">
                <label class="form-label small mb-1">{{ __('Search') }}</label>
                <input type="search" class="form-control" name="q" value="{{ $term }}">
            </div>
            <div class="col-auto"><button class="btn btn-light-secondary" type="submit">{{ __('Show') }}</button></div>
        </form>

        <table class="table table-hover w-100 keep-table">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('No.') }}</th><th>{{ __('Details') }}</th><th>{{ __('Account') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th class="no-print"></th></tr></thead>
            <tbody>
                @forelse ($entries as $entry)
                    @foreach ($entry->lines as $line)
                        <tr class="{{ $entry->isVoid() ? 'text-decoration-line-through text-muted' : '' }}">
                            @if ($loop->first)
                                <td class="text-nowrap" rowspan="{{ $entry->lines->count() }}">{{ fmt_date($entry->date) }}</td>
                                <td class="text-nowrap" rowspan="{{ $entry->lines->count() }}">{{ $entry->number }}</td>
                                <td rowspan="{{ $entry->lines->count() }}">{{ $entry->description }}<div class="small text-muted">{{ $entry->creator?->name }}</div></td>
                            @endif
                            <td>{{ $line->account->name }}</td>
                            <td class="text-end text-nowrap">{{ \App\Support\Num::isZero($line->debit) ? '' : money($line->debit, $line->account->currency->code) }}</td>
                            <td class="text-end text-nowrap">{{ \App\Support\Num::isZero($line->credit) ? '' : money($line->credit, $line->account->currency->code) }}</td>
                            @if ($loop->first)
                                <td class="text-end no-print" rowspan="{{ $entry->lines->count() }}">
                                    @if ($entry->type === 'journal' && ! $entry->isVoid())
                                        @can('journal.manage')
                                            <form method="POST" action="{{ route('journal.destroy', ['id' => $entry->id]) }}" data-confirm="{{ __('Cancel this entry?') }}" data-confirm-ok="{{ __('Cancel it') }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light-danger">{{ __('Cancel') }}</button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">{{ __('Nothing was recorded on these days.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $entries->links('partials.pagination') }}
    </div>
</div>
@endsection
