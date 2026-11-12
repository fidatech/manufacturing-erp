@extends(request()->boolean('print') ? 'layouts.print' : 'layouts.app', ['title' => $title])

@section('content')
<x-page-header :title="$title" icon="cart-flatbed" :crumbs="[[\App\Http\Controllers\Trade\PurchaseController::names()[$purchase->type], route('purchases.index', ['type' => $purchase->type])], [$purchase->number]]">
    <a class="btn btn-outline-soft" href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank" rel="noopener"><x-icon name="print" class="me-1" /> {{ __('Print') }}</a>
    @can('purchases.manage')
        @if ($purchase->type === 'quotation' && $purchase->status === 'open')
            <a class="btn btn-outline-soft" href="{{ route('purchases.create', ['type' => 'order', 'from' => $purchase->id]) }}">{{ __('Turn into an order') }}</a>
        @endif
        @if (in_array($purchase->type, ['order', 'quotation'], true) && $purchase->status === 'open')
            <a class="btn btn-primary" href="{{ route('purchases.create', ['type' => 'invoice', 'from' => $purchase->id]) }}">{{ __('Turn into a purchase') }}</a>
        @endif
        @if ($purchase->type === 'invoice' && $purchase->status === 'posted')
            <a class="btn btn-outline-soft" href="{{ route('purchases.create', ['type' => 'return', 'from' => $purchase->id]) }}">{{ __('Return goods') }}</a>
        @endif
        @if ($purchase->status !== 'cancelled')
            <form method="POST" action="{{ route('purchases.cancel', ['id' => $purchase->id]) }}" class="d-inline" data-confirm="{{ __('Cancel :number?', ['number' => $purchase->number]) }}" data-confirm-text="{{ __('Its stock and money entries will no longer count.') }}" data-confirm-ok="{{ __('Cancel it') }}">
                @csrf
                <button type="submit" class="btn btn-light-danger">{{ __('Cancel') }}</button>
            </form>
        @endif
    @endcan
</x-page-header>

@if ($purchase->type === 'invoice' && $purchase->status === 'posted' && ! $purchase->received_date)
    @can('purchases.manage')
        <form method="POST" action="{{ route('purchases.receive', ['id' => $purchase->id]) }}" class="card mb-4 no-print">
            @csrf
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md"><strong>{{ __('These goods are on the way.') }}</strong> {{ __('When they arrive, write the date and press the button. They then enter the warehouse.') }}</div>
                    <div class="col-8 col-md-auto"><x-date name="date" :value="today_iso()" required /></div>
                    <div class="col-4 col-md-auto"><button type="submit" class="btn btn-success w-100">{{ __('Goods received') }}</button></div>
                </div>
            </div>
        </form>
    @endcan
@endif

<div class="card">
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Date') }}</div><strong>{{ fmt_date($purchase->date) }}</strong></div>
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Supplier') }}</div><strong><a href="{{ route('statement', ['id' => $purchase->supplier_id]) }}">{{ $purchase->supplier->name }}</a></strong></div>
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Warehouse') }}</div><strong>{{ $purchase->warehouse->name }}</strong></div>
            <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Status') }}</div>@include('purchases.status')</div>
            @if ($purchase->supplier_invoice_no)
                <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Supplier\'s bill number') }}</div><strong>{{ $purchase->supplier_invoice_no }}</strong></div>
            @endif
            @if ($purchase->received_date)
                <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Goods received') }} ({{ $purchase->grn_number }})</div><strong>{{ fmt_date($purchase->received_date) }}</strong></div>
            @endif
            @if ($purchase->parent)
                <div class="col-6 col-md-3"><div class="small text-muted">{{ __('Made from') }}</div><strong><a href="{{ route('purchases.show', ['id' => $purchase->parent_id]) }}">{{ $purchase->parent->number }}</a></strong></div>
            @endif
        </div>

        <table class="table w-100 keep-table">
            <thead><tr><th>#</th><th>{{ __('Item') }}</th><th>{{ __('Batch') }}</th><th>{{ __('Expires on') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Total') }}</th>@if ($purchase->type === 'invoice')<th class="text-end">{{ __('Cost each (:base)', ['base' => $base]) }}</th>@endif</tr></thead>
            <tbody>
                @foreach ($purchase->lines as $line)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $line->item->label() }}</td>
                        <td>{{ $line->batch_no }}</td>
                        <td>{{ fmt_date($line->expiry_date) }}</td>
                        <td class="text-end text-nowrap">{{ qty($line->qty) }} {{ $line->item->unit->symbol }}</td>
                        <td class="text-end text-nowrap">{{ \App\Support\Num::format($line->price, 6, true) }}</td>
                        <td class="text-end text-nowrap">{{ money($line->total) }}</td>
                        @if ($purchase->type === 'invoice')
                            <td class="text-end text-nowrap">{{ \App\Support\Num::format($line->unit_cost_base, 4, true) }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="row justify-content-end">
            <div class="col-12 col-md-5">
                <div class="d-flex justify-content-between py-1"><span>{{ __('Total of items') }}</span><strong>{{ money($purchase->subtotal, $purchase->currency->code) }}</strong></div>
                @unless (\App\Support\Num::isZero($purchase->discount))
                    <div class="d-flex justify-content-between py-1"><span>{{ __('Discount') }}</span><strong>{{ money($purchase->discount, $purchase->currency->code) }}</strong></div>
                @endunless
                <div class="d-flex justify-content-between py-1 fs-5"><span>{{ __('Total') }}</span><strong>{{ money($purchase->total, $purchase->currency->code) }}</strong></div>
                @foreach ($purchase->costs as $cost)
                    <div class="d-flex justify-content-between py-1 text-muted"><span>{{ $cost->name }} · {{ $cost->paidFrom->name }}</span><span>{{ money($cost->amount, $cost->paidFrom->currency->code) }}</span></div>
                @endforeach
                @unless (\App\Support\Num::isZero($purchase->paid))
                    <div class="d-flex justify-content-between py-1"><span>{{ __('Paid') }} · {{ $purchase->paidFrom?->name }}</span><strong>{{ money($purchase->paid, $purchase->currency->code) }}</strong></div>
                @endunless
            </div>
        </div>
        @if ($purchase->notes)
            <p class="mt-3 mb-0"><span class="text-muted">{{ __('Notes') }}:</span> {{ $purchase->notes }}</p>
        @endif
    </div>
</div>

@include('partials.attachments', ['subjectType' => 'Purchase', 'subjectId' => $purchase->id])
@endsection
