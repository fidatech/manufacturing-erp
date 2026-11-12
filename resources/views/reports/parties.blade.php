@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => $title])

@section('content')
<x-page-header :title="$title" icon="users" :crumbs="[[__('Reports'), route('reports.index')], [$title]]" />

<div class="card">
    <div class="card-body">
        @include('reports.filter', ['range' => false, 'heading' => $title])
        <table class="table table-hover w-100">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Phone') }}</th><th class="text-end">{{ __('Balance') }}</th><th class="text-end">{{ __('In :base', ['base' => $base]) }}</th><th class="text-end no-print">{{ __('Actions') }}</th></tr></thead>
            <tbody>
                @forelse ($rows as $account)
                    <tr>
                        <td>{{ $account->name }}{{ $account->company ? ' · '.$account->company : '' }}</td>
                        <td>{{ __($account->type->name) }}</td>
                        <td>{{ $account->phone }}</td>
                        <td class="text-end text-nowrap">{{ $account->balanceHtml($account->own) }}</td>
                        <td class="text-end text-nowrap">{{ money($account->base) }}</td>
                        <td class="text-end no-print"><a class="btn btn-sm btn-light-secondary" href="{{ route('statement', ['id' => $account->id]) }}">{{ __('Statement') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('Nobody') }}</td></tr>
                @endforelse
                <tr class="fw-bold"><td colspan="4">{{ __('Total') }}</td><td class="text-end text-nowrap">{{ money($total, $base) }}</td><td class="no-print"></td></tr>
            </tbody>
        </table>
        <div class="form-text">{{ __('A plus amount means they owe us; a minus amount means we owe them.') }}</div>
    </div>
</div>
@endsection
