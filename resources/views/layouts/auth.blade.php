<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ text_dir() }}" data-bs-theme="light" data-primary="blue" data-icons="{{ asset_v('icons.svg') }}">
<head>
    @include('partials.head')
</head>
<body class="auth-body">
<div class="auth-shell">
    <aside class="auth-hero">
        <a href="{{ route('login') }}" class="brand-link"><x-brand-logo :size="36" inverse /><span class="brand-text">{{ config('app.name') }}</span></a>
        <div>
            <span class="ah-kicker"><x-icon name="industry" /> {{ __('app.auth.hero_kicker') }}</span>
            <h1>{{ __('app.auth.hero_title') }}</h1>
            <p>{{ __('app.auth.hero_text') }}</p>
        </div>
        <div></div>
    </aside>
    <main class="auth-main">
        <div class="auth-tools">
            <button class="topbar-icon" type="button" data-theme-toggle title="{{ __('app.nav.toggle_theme') }}"><x-icon name="moon" /></button>
        </div>
        <div class="auth-mobile-hero"><span class="amh-logo"><x-brand-logo :size="44" /></span><strong>{{ config('app.name') }}</strong><small>{{ __('app.auth.hero_kicker') }}</small></div>
        @yield('content')
        <footer class="mt-4 small text-muted">© {{ date('Y') }} {{ config('app.name') }} · <a href="https://fida.af" target="_blank" rel="noopener">Fida Technologies</a></footer>
    </main>
</div>
@include('partials.flash')
@include('partials.scripts')
</body>
</html>
