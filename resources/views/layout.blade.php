<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1d1b4b">
    <title>@yield('title', 'Seu espaço') · habittos</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('imgs/habbitos-icone.svg') }}">
    <link rel="icon" type="image/png" href="{{ asset('imgs/habbitos-icone.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('imgs/habbitos-icone.png') }}">
    <script src="{{ asset('theme.js') }}" data-navigate-once></script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}" data-navigate-track>
    <link rel="stylesheet" href="{{ asset('css/interface.css') }}" data-navigate-track>
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}" data-navigate-track>
    <link rel="stylesheet" href="{{ asset('css/goals.css') }}" data-navigate-track>
    @livewireStyles
</head>

<body>
    @auth
        @if (request()->routeIs('onboarding'))
            <header class="onboarding-header">
                <a class="brand" href="{{ route('onboarding') }}"><img class="brand-icon" src="{{ asset('imgs/habbitos-icone.svg') }}" width="44" height="44" alt="">habittos<span class="brand-dot">.</span></a>
                <div class="actions">
                    <x-theme-toggle />
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-button">Sair da conta</button></form>
                </div>
            </header>
            <main class="main onboarding-main">
                <div class="content">
                    @yield('content')
                </div>
            </main>
        @else
        <div class="app-shell">
            <aside class="sidebar">
                <a wire:navigate class="brand" href="{{ route('home') }}"><img class="brand-icon"
                        src="{{ asset('imgs/habbitos-icone.svg') }}" width="44" height="44" alt="">habittos<span class="brand-dot">.</span></a>
                <livewire:workspace-switcher :active-id="$activeWorkspace?->id" :module="request()->routeIs('home') ? 'home' : (request()->routeIs('finance.*') ? request()->route()->getName() : (request()->routeIs('settings.*')
                    ? 'settings'
                    : (request()->routeIs('goals.*')
                        ? request()->route()->getName()
                        : (request()->routeIs('members.*')
                            ? 'members'
                            : 'finance'))))" :month="$month ??
                    (isset($entry) ? $entry->date->format('Y-m') : request('month', now()->format('Y-m')))" />
                <span class="nav-label">SEU DIA A DIA</span>
                <nav aria-label="Navegação principal">
                    <a wire:navigate class="{{ request()->routeIs('home') ? 'active' : '' }}"
                        href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>
                        <x-icon name="dashboard" /><span>Home</span>
                    </a>
                    @if ($activeWorkspace)
                        @can('finance.view', $activeWorkspace)
                            <x-finance-navigation :workspace="$activeWorkspace"
                                :month="$month ?? (isset($entry) ? $entry->date->format('Y-m') : request('month', app(\App\Services\FinancialCalendar::class)->monthForDate($activeWorkspace, now()->toDateString())))" />
                        @endcan
                        @can('goals.view', $activeWorkspace)
                            <x-goals-navigation :workspace="$activeWorkspace" />
                        @endcan
                        @can('members.manage', $activeWorkspace)
                            <a wire:navigate class="{{ request()->routeIs('members.*') ? 'active' : '' }}"
                                href="{{ route('members.index', $activeWorkspace) }}"><x-icon name="members" /><span>Pessoas e
                                    acessos</span></a>
                        @endcan
                        <a wire:navigate class="{{ request()->routeIs('settings.*') ? 'active' : '' }}"
                            href="{{ route('settings.index', $activeWorkspace) }}"><x-icon
                                name="settings" /><span>Configurações</span></a>
                    @endif
                </nav>

                <div class="profile"><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                    <div>{{ auth()->user()->name }}<form method="POST" action="{{ route('logout') }}">@csrf<button
                                class="text-button">Sair da conta</button></form>
                    </div>
                </div>
            </aside>
            <main class="main">
                <header class="topbar"><span>{{ $activeWorkspace?->name ?? 'Meu espaço' }}<span
                            class="breadcrumb-divider">/</span><span class="muted">@yield('title')</span></span>
                    <div class="topbar-actions">
                        <span class="topbar-tag"><span class="tiny-dot"></span> Um passo de cada vez</span>
                        <x-theme-toggle />
                    </div>
                </header>
                <div class="content page-enter {{ request()->routeIs('home') ? 'home-content' : '' }}">
                    @include('partials.feedback')
                    @yield('content')
                </div>
            </main>
        </div>
        @endif
    @else
        <main class="auth-shell">
            <div class="auth-intro"><a wire:navigate class="brand" href="/"><img class="brand-logo"
                        src="{{ asset('imgs/logo-completa.svg') }}" width="240" height="161" alt="Habittos — Evolua todo dia"></a><span class="eyebrow">ESPAÇO PARA O QUE IMPORTA</span>
                <h1>Pequenos hábitos.<br>Uma vida mais leve.</h1>
                <p>Suas finanças, seus planos e as pessoas que fazem parte deles. Tudo no mesmo lugar.</p>
                <div class="auth-art"><span>Organizar</span><span>Planejar</span><span>Realizar</span></div>
            </div>
            <div class="auth-content">
                @include('partials.feedback')
                @yield('content')
            </div>
        </main>
    @endauth
    <script src="{{ asset('money.js') }}" data-navigate-once></script>
    <script src="{{ asset('cover-position.js') }}" data-navigate-once></script>
    <script src="{{ asset('category-pie.js') }}" data-navigate-once></script>
    <script src="{{ asset('goals-kanban.js') }}" data-navigate-once></script>
    <script src="{{ asset('goal-description.js') }}" data-navigate-once></script>
    <script src="{{ asset('app.js') }}" data-navigate-once></script>
    @livewireScripts
</body>

</html>
