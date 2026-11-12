@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => __('Cash flow')])

@section('content')
<x-page-header :title="__('Cash flow')" icon="money-bill-wave" :crumbs="[[__('Reports'), route('reports.index')], [__('Cash flow')]]" />

<div class="card mb-4">
    <div class="card-body">
        @include('reports.filter', ['heading' => __('Cash flow')])
        <table class="table table-hover w-100 keep-table">
            <thead><tr><th>{{ __('Cash box, bank or sarafi') }}</th><th class="text-end">{{ __('At the start') }}</th><th class="text-end">{{ __('In') }}</th><th class="text-end">{{ __('Out') }}</th><th class="text-end">{{ __('At the end') }}</th></tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td><a href="{{ route('statement', ['id' => $row['account']->id, 'from' => $from, 'to' => $to]) }}">{{ $row['account']->name }}</a> <span class="text-muted">{{ $row['account']->currency->code }}</span></td>
                        <td class="text-end">{{ money($row['opening']) }}</td>
                        <td class="text-end text-success">{{ money($row['in']) }}</td>
                        <td class="text-end text-danger">{{ money($row['out']) }}</td>
                        <td class="text-end fw-semibold">{{ money($row['closing']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="form-text">{{ __('Each row is in the currency of its own account.') }}</div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title">{{ __('Where the money came from and went (:base)', ['base' => $base]) }}</h5></div>
    <div class="card-body">
        <table class="table w-100 keep-table">
            <thead><tr><th>{{ __('Reason') }}</th><th class="text-end">{{ __('In') }}</th><th class="text-end">{{ __('Out') }}</th></tr></thead>
            <tbody>
                @forelse ($byType as $row)
                    <tr><td>{{ $typeNames[$row->type] ?? $row->type }}</td><td class="text-end">{{ money($row->money_in) }}</td><td class="text-end">{{ money($row->money_out) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-3">{{ __('No money moved in these days.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
