@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => $title])

@section('content')
<x-page-header :title="$title" icon="right-left" :crumbs="[[\App\Http\Controllers\Inventory\StockDocController::names()[$type], route('stock-docs.index', ['type' => $type])], [$doc->number]]">
    <a class="btn btn-outline-soft" href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank" rel="noopener"><x-icon name="print" class="me-1" /> {{ __('Print') }}</a>
    @can('inventory.manage')
        @if ($doc->status !== 'cancelled')
            <form method="POST" action="{{ route('stock-docs.cancel', ['type' => $type, 'id' => $doc->id]) }}" class="d-inline" data-confirm="{{ __('Cancel :number?', ['number' => $doc->number]) }}" data-confirm-ok="{{ __('Cancel it') }}">
                @csrf
                <button type="submit" class="btn btn-light-danger">{{ __('Cancel') }}</button>
            </form>
        @endif
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Date') }}</div><strong>{{ fmt_date($doc->date) }}</strong></div>
            <div class="col-6 col-md-5"><div class="small text-muted">{{ __('Warehouse') }}</div><strong>{{ $doc->warehouse->name }}{{ $doc->toWarehouse ? ' → '.$doc->toWarehouse->name : '' }}</strong></div>
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Status') }}</div><span class="badge-soft {{ $doc->status === 'cancelled' ? 'danger' : 'success' }}">{{ $doc->status === 'cancelled' ? __('Cancelled') : __('Done') }}</span></div>
        </div>
        <table class="table w-100 keep-table">
            <thead>
                <tr>
                    <th>#</th><th>{{ __('Item') }}</th>
                    @if ($type === 'count')
                        <th class="text-end">{{ __('System said') }}</th><th class="text-end">{{ __('Counted') }}</th><th class="text-end">{{ __('Difference') }}</th>
                    @else
                        <th class="text-end">{{ __('Quantity') }}</th>
                    @endif
                    @if ($type === 'opening')
                        <th class="text-end">{{ __('Cost each (:base)', ['base' => $base]) }}</th><th>{{ __('Batch') }}</th><th>{{ __('Expires on') }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($doc->lines as $line)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $line->item->label() }}</td>
                        @if ($type === 'count')
                            <td class="text-end">{{ qty($line->system_qty) }}</td>
                            <td class="text-end">{{ qty($line->qty) }}</td>
                            <td class="text-end fw-semibold">{{ qty(\App\Support\Num::sub($line->qty, $line->system_qty)) }}</td>
                        @else
                            <td class="text-end text-nowrap">{{ qty($line->qty) }} {{ $line->item->unit->symbol }}</td>
                        @endif
                        @if ($type === 'opening')
                            <td class="text-end">{{ \App\Support\Num::format($line->unit_cost, 4, true) }}</td><td>{{ $line->batch_no }}</td><td>{{ fmt_date($line->expiry_date) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if ($doc->notes)
            <p class="mt-3 mb-0"><span class="text-muted">{{ __('Notes') }}:</span> {{ $doc->notes }}</p>
        @endif
    </div>
</div>
@endsection
