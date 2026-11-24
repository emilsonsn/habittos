@props(['workspace'])
<details class="finance-nav" name="sidebar-navigation" @if (request()->routeIs('studies.*')) open @endif>
    <summary aria-label="Estudos" title="Estudos" class="finance-nav-trigger {{ request()->routeIs('studies.*') ? 'active' : '' }}">
        <x-icon name="studies" /><span>Estudos</span><span class="finance-nav-chevron" aria-hidden="true">⌄</span>
    </summary>
    <div class="finance-submenu" aria-label="Seções de estudos">
        @foreach (\App\Enums\StudySection::cases() as $item)
            <a wire:navigate href="{{ route($item->routeName(), $workspace) }}" @class(['active' => request()->routeIs($item->routeName())])
                @if (request()->routeIs($item->routeName())) aria-current="page" @endif>{{ $item->label() }}</a>
        @endforeach
    </div>
</details>
