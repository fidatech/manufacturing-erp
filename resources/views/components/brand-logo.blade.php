@props(['size' => 32, 'inverse' => false])
@php
    $gradient = 'lg'.\Illuminate\Support\Str::random(6);
@endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
@if ($inverse)
<rect width="40" height="40" rx="11" fill="#fff"/><path d="M12 11h16v4.2H17v4.3h9.5v4.1H17V30h-5V11z" style="fill:var(--primary)"/><circle cx="28.5" cy="27.5" r="3" style="fill:var(--primary)" opacity=".85"/>
@else
<rect width="40" height="40" rx="11" fill="url(#{{ $gradient }})"/><path d="M12 11h16v4.2H17v4.3h9.5v4.1H17V30h-5V11z" fill="#fff"/><circle cx="28.5" cy="27.5" r="3" fill="#fff" opacity=".85"/>
<defs><linearGradient id="{{ $gradient }}" x1="0" y1="0" x2="40" y2="40"><stop style="stop-color:var(--brand-from,#4a8df8)"/><stop offset="1" style="stop-color:var(--brand-to,#5b45e0)"/></linearGradient></defs>
@endif
</svg>
