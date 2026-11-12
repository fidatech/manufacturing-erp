@php
    $user = auth()->user();
@endphp
<header class="app-topbar" id="appTopbar">
    <button class="btn-icon btn-ghost topbar-toggle" id="sidebarToggle" type="button" aria-label="{{ __('app.nav.toggle_sidebar') }}"><x-icon name="bars-staggered" /></button>
    <button class="appbar-back" type="button" id="appBack" aria-label="{{ __('app.nav.back') }}"><x-icon name="chevron-left" /></button>
    <div class="appbar-title" id="appbarTitle">{{ $title ?? config('app.name') }}</div>

    <button class="topbar-search" type="button" data-bs-toggle="modal" data-bs-target="#searchModal">
        <x-icon name="magnifying-glass" /><span>{{ __('app.nav.search_placeholder') }}</span><span class="kbd-group"><kbd>Ctrl</kbd><kbd>K</kbd></span>
    </button>

    <div class="topbar-right">
        @can('setup.manage')
            <button class="topbar-icon" type="button" data-bs-toggle="modal" data-bs-target="#ratesModal" title="{{ __('Today\'s exchange rates') }}" aria-label="{{ __('Today\'s exchange rates') }}"><x-icon name="coins" /></button>
        @endcan

        <div class="dropdown">
            <button class="topbar-icon topbar-lang" data-bs-toggle="dropdown" type="button" aria-expanded="false" title="{{ __('Language') }}" aria-label="{{ __('Language') }}"><x-icon name="globe" /><span>{{ strtoupper(app()->getLocale()) }}</span></button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li class="dropdown-header">{{ __('Language') }}</li>
                @foreach (config('app.locales') as $code => $language)
                    <li><button class="dropdown-item {{ app()->getLocale() === $code ? 'active' : '' }}" type="submit" form="localeForm" name="locale" value="{{ $code }}" lang="{{ $code }}">{{ $language }}</button></li>
                @endforeach
            </ul>
        </div>

        <button class="topbar-icon" type="button" data-theme-toggle title="{{ __('app.nav.toggle_theme') }}"><x-icon name="moon" /></button>

        <div class="dropdown">
            <button class="topbar-user" data-bs-toggle="dropdown" type="button" aria-expanded="false">
                <span class="avatar avatar-sm avatar-soft-primary">{{ $user->initials() }}</span>
                <span class="topbar-user-meta"><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end user-menu">
                <li class="user-menu-head"><span class="avatar avatar-md avatar-soft-primary">{{ $user->initials() }}</span><div><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></div></li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item" type="submit" form="calendarForm"><x-icon name="calendar-days" /> {{ calendar() === 'shamsi' ? __('Dates: Solar Hijri. Switch to Gregorian') : __('Dates: Gregorian. Switch to Solar Hijri') }}</button></li>
                <li><hr class="dropdown-divider"></li>
                <li><button class="dropdown-item text-danger" type="submit" form="logoutForm"><x-icon name="right-from-bracket" /> {{ __('app.nav.sign_out') }}</button></li>
            </ul>
        </div>
    </div>
</header>
