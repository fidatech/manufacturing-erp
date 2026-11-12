@php
    $erpText = [
        'cancel' => __('app.actions.cancel'),
        'confirmOk' => __('app.actions.yes'),
        'confirmTitle' => __('app.actions.confirm_title'),
        'noResults' => __('app.nav.no_results'),
        'addNew' => __('New'),
        'failed' => __('Something went wrong. Please try again.'),
    ];
@endphp
<script>window.ERP_TEXT = {{ Illuminate\Support\Js::from($erpText) }};</script>
<script src="{{ asset_v('js/app.js') }}" defer></script>
@stack('scripts')
