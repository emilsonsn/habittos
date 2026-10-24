<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1d1b4b">
    <title>@yield('title', 'Seu espaço') · habittos</title>
    <script src="{{ asset('theme.js') }}" data-navigate-once></script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}" data-navigate-track>
    <link rel="stylesheet" href="{{ asset('css/interface.css') }}" data-navigate-track>
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}" data-navigate-track>
    @livewireStyles
</head>

<body>
    @auth
        <div class="app-shell">
            <aside class="sidebar">
                <a wire:navigate class="brand" href="{{ route('home') }}"><span
                        class="brand-mark">h</span>habittos<span class="brand-dot">.</span></a>
                <livewire:workspace-switcher :active-id="$activeWorkspace?->id" :module="request()->routeIs('home') ? 'home' : (request()->routeIs('finance.*') ? request()->route()->getName() : (request()->routeIs('settings.*')
                    ? 'settings'
                    : (request()->routeIs('goals.*')
                        ? 'goals'
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
                            <a wire:navigate class="{{ request()->routeIs('goals.*') ? 'active' : '' }}"
                                href="{{ route('goals.index', $activeWorkspace) }}"><x-icon
                                    name="goals" /><span>Metas</span></a>
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
                <div class="content page-enter">
                    @include('partials.feedback')
                    @yield('content')
                </div>
            </main>
        </div>
    @else
        <main class="auth-shell">
            <div class="auth-intro"><a wire:navigate class="brand" href="/">habittos<span
                        class="brand-dot">.</span></a><span class="eyebrow">ESPAÇO PARA O QUE IMPORTA</span>
                <h1>Pequenos hábitos.<br>Uma vida mais leve.</h1>
                <p>Suas finanças, seus planos e as pessoas que fazem parte deles. Tudo no mesmo lugar.</p>
                <div class="auth-art"><span>Organizar</span><span>Planejar</span><span>Realizar</span></div>
            </div>
            <div class="auth-content">
                <div class="auth-theme-toggle"><x-theme-toggle /></div>
                @include('partials.feedback')
                @yield('content')
            </div>
        </main>
    @endauth
    <script src="{{ asset('money.js') }}" data-navigate-once></script>
    <script src="{{ asset('app.js') }}" data-navigate-once></script>
    @livewireScripts
</body>

</html>
