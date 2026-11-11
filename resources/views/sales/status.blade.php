@if ($sale->status === 'cancelled')
    <span class="badge-soft danger">{{ __('Cancelled') }}</span>
@elseif (in_array($sale->type, ['order', 'quotation'], true))
    <span class="badge-soft {{ $sale->status === 'converted' ? 'success' : 'warning' }}">{{ $sale->status === 'converted' ? __('Used') : __('Open') }}</span>
@elseif ($sale->type === 'invoice')
    <span class="badge-soft {{ ['pending' => 'warning', 'dispatched' => 'info', 'delivered' => 'success'][$sale->delivery_status] ?? 'warning' }}">{{ ['pending' => __('Not sent yet'), 'dispatched' => __('On the way'), 'delivered' => __('Delivered')][$sale->delivery_status] ?? '' }}</span>
@else
    <span class="badge-soft success">{{ __('Done') }}</span>
@endif
