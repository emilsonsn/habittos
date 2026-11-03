@props(['workspace'])
<details class="finance-nav" name="sidebar-navigation" @if (request()->routeIs('notes.*')) open @endif>
    <summary class="finance-nav-trigger {{ request()->routeIs('notes.*') ? 'active' : '' }}">
        <x-icon name="notes" /><span>Notas</span><span class="finance-nav-chevron" aria-hidden="true">⌄</span>
    </summary>
    <div class="finance-submenu" aria-label="Seções de notas">
        @foreach (\App\Enums\NoteSection::cases() as $item)
            <a wire:navigate href="{{ route($item->routeName(), $workspace) }}"
                @class(['active' => request()->routeIs($item->routeName())])
                @if (request()->routeIs($item->routeName())) aria-current="page" @endif>{{ $item->label() }}</a>
        @endforeach
    </div>
</details>
