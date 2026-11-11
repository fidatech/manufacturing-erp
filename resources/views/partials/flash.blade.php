{{-- Messages set with ->with('success', '...') and friends are shown as toasts. --}}
@foreach (['success', 'warning', 'danger', 'info'] as $type)
    @if (session($type))
        <div data-toast="{{ $type }}" hidden>{{ session($type) }}</div>
    @endif
@endforeach
