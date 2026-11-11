@if ($purchase->status === 'cancelled')
    <span class="badge-soft danger">{{ __('Cancelled') }}</span>
@elseif (in_array($purchase->type, ['order', 'quotation'], true))
    <span class="badge-soft {{ $purchase->status === 'converted' ? 'success' : 'warning' }}">{{ $purchase->status === 'converted' ? __('Used') : __('Open') }}</span>
@elseif ($purchase->type === 'invoice')
    <span class="badge-soft {{ $purchase->received_date ? 'success' : 'warning' }}">{{ $purchase->received_date ? __('Received') : __('On the way') }}</span>
@else
    <span class="badge-soft success">{{ __('Done') }}</span>
@endif
