<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1d1b4b">
    <title>@yield('title', 'Seu espaço') · habittos</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}" data-navigate-track>
    <link rel="stylesheet" href="{{ asset('css/interface.css') }}" data-navigate-track>
    @livewireStyles
</head>

<body>
    @auth
        <div class="app-shell">
            <aside class="sidebar">
                <a wire:navigate class="brand" href="{{ route('workspaces.index') }}"><span
                        class="brand-mark">h</span>habittos<span class="brand-dot">.</span></a>
                <livewire:workspace-switcher :active-id="$activeWorkspace?->id" :module="request()->routeIs('settings.*')
                    ? 'settings'
                    : (request()->routeIs('goals.*')
                        ? 'goals'
                        : (request()->routeIs('members.*')
                            ? 'members'
                            : 'finance'))" :month="$month ??
                    (isset($entry) ? $entry->date->format('Y-m') : request('month', now()->format('Y-m')))" />
                <span class="nav-label">SEU DIA A DIA</span>
                <nav aria-label="Navegação principal">
                    @if ($activeWorkspace)
                        @can('finance.view', $activeWorkspace)
                            <a wire:navigate class="{{ request()->routeIs('finance.*', 'entries.*') ? 'active' : '' }}"
                                href="{{ route('finance.index', $activeWorkspace) }}"><x-icon
                                    name="finance" /><span>Financeiro</span></a>
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
                <div class="sidebar-note"><span class="tiny-dot"></span> No seu ritmo.<p>Um pouco de organização.<br>Mais
                        espaço para viver.</p>
                </div>
                <div class="profile"><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                    <div>{{ auth()->user()->name }}<form method="POST" action="{{ route('logout') }}">@csrf<button
                                class="text-button">Sair da conta</button></form>
                    </div>
                </div>
            </aside>
            <main class="main">
                <header class="topbar"><span>{{ $activeWorkspace?->name ?? 'Meu espaço' }}<span
                            class="breadcrumb-divider">/</span><span class="muted">@yield('title')</span></span><span
                        class="topbar-tag"><span class="tiny-dot"></span> Um passo de cada vez</span></header>
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
                @include('partials.feedback')
                @yield('content')
            </div>
        </main>
    @endauth
    @livewireScripts
    <script src="{{ asset('app.js') }}" data-navigate-once></script>
</body>

</html>
