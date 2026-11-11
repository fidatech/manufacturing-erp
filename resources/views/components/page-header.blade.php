{{--
    Page title bar.
    <x-page-header :title="__('...')" icon="box" :crumbs="[[__('app.nav.home'), route('dashboard')], [__('...')]]">
        buttons go here
    </x-page-header>
--}}
@props(['title', 'icon' => null, 'crumbs' => [], 'subtitle' => null])
<div class="page-header card-soft is-inline">
    <div class="page-header-main">
        @if ($icon)
            <span class="page-header-icon"><x-icon :name="$icon" /></span>
        @endif
        <div class="page-header-text">
            <h1>{{ $title }}</h1>
            @if ($crumbs)
                <nav class="crumbs" aria-label="breadcrumb">
                    @foreach ($crumbs as $crumb)
                        @if (! empty($crumb[1]))<a href="{{ $crumb[1] }}">{{ $crumb[0] }}</a>@else<span class="{{ $loop->last ? 'current' : '' }}">{{ $crumb[0] }}</span>@endif
                        @unless ($loop->last)<i class="sep">/</i>@endunless
                    @endforeach
                </nav>
            @endif
            @if ($subtitle)
                <p class="page-header-sub">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @if (trim($slot) !== '')
        <div class="page-header-actions">{{ $slot }}</div>
    @endif
</div>
