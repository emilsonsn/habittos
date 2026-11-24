@props(['workspace'])
<details class="finance-nav" name="sidebar-navigation" @if (request()->routeIs('goals.*')) open @endif>
    <summary aria-label="Metas" title="Metas" class="finance-nav-trigger {{ request()->routeIs('goals.*') ? 'active' : '' }}">
        <x-icon name="goals" /><span>Metas</span><span class="finance-nav-chevron" aria-hidden="true">⌄</span>
    </summary>
    <div class="finance-submenu" aria-label="Seções de metas">
        @foreach ([\App\Enums\GoalSection::Dashboard, \App\Enums\GoalSection::Weekly, \App\Enums\GoalSection::Monthly, \App\Enums\GoalSection::Annual] as $item)
            <a wire:navigate href="{{ route($item->routeName(), $workspace) }}"
                @class(['active' => request()->routeIs($item->routeName())])
                @if (request()->routeIs($item->routeName())) aria-current="page" @endif>{{ $item->label() }}</a>
        @endforeach
    </div>
</details>
