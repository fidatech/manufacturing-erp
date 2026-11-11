@php
    $user = auth()->user();
@endphp
<aside class="app-sidebar" id="appSidebar" aria-label="{{ __('app.nav.menu') }}">
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="brand-link"><x-brand-logo /><span class="brand-text">{{ config('app.name') }}</span></a>
    </div>
    <div class="sidebar-scroll" id="sidebarScroll">
        <div class="drawer-profile">
            <span class="avatar avatar-md avatar-soft-primary">{{ $user->initials() }}</span>
            <div class="min-w-0"><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></div>
        </div>
        <div class="drawer-quick">
            <button type="button" data-theme-toggle><x-icon name="moon" /><span>{{ __('app.nav.theme') }}</span></button>
            <button type="button" data-bs-toggle="modal" data-bs-target="#searchModal"><x-icon name="magnifying-glass" /><span>{{ __('app.nav.search') }}</span></button>
            <button type="submit" form="logoutForm"><x-icon name="right-from-bracket" /><span>{{ __('app.nav.sign_out') }}</span></button>
        </div>
        <ul class="sidebar-menu" id="sidebarMenu">
            @foreach (\App\Support\Menu::for($user) as $item)
                @if (isset($item['heading']))
                    <li class="menu-heading"><span>{{ __($item['heading']) }}</span></li>
                @elseif (isset($item['children']))
                    @php
                        $open = collect($item['children'])->contains(fn ($child) => \App\Support\Menu::isActive($child));
                    @endphp
                    <li class="menu-item has-sub {{ $open ? 'open' : '' }}">
                        <a class="menu-link" href="#menu-{{ $loop->index }}" data-bs-toggle="collapse" role="button" aria-expanded="{{ $open ? 'true' : 'false' }}"><span class="menu-icon"><x-icon :name="$item['icon']" /></span><span class="menu-text">{{ __($item['label']) }}</span><x-icon name="chevron-right" class="menu-arrow" /></a>
                        <ul class="menu-sub collapse {{ $open ? 'show' : '' }}" id="menu-{{ $loop->index }}">
                            @foreach ($item['children'] as $child)
                                <li><a class="menu-sublink {{ \App\Support\Menu::isActive($child) ? 'active' : '' }}" href="{{ \App\Support\Menu::url($child) }}">{{ __($child['label']) }}</a></li>
                            @endforeach
                        </ul>
                    </li>
                @else
                    <li class="menu-item {{ \App\Support\Menu::isActive($item) ? 'active' : '' }}">
                        <a class="menu-link" href="{{ \App\Support\Menu::url($item) }}"><span class="menu-icon"><x-icon :name="$item['icon']" /></span><span class="menu-text">{{ __($item['label']) }}</span></a>
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
