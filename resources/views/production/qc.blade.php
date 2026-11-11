@if ($batch->status === 'cancelled')
    <span class="badge-soft danger">{{ __('Cancelled') }}</span>
@else
    <span class="badge-soft {{ ['pending' => 'warning', 'passed' => 'success', 'failed' => 'danger'][$batch->qc_status] ?? 'warning' }}">{{ ['pending' => __('Not tested yet'), 'passed' => __('Lab: passed'), 'failed' => __('Lab: failed')][$batch->qc_status] ?? '' }}</span>
@endif
