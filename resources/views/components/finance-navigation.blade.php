@props(['workspace', 'month'])
<details class="finance-nav" @if (request()->routeIs('finance.*', 'entries.*')) open @endif
    x-data="{ month: @js($month) }" @financial-month-changed.window="month = $event.detail.month">
    <summary class="finance-nav-trigger {{ request()->routeIs('finance.*', 'entries.*') ? 'active' : '' }}">
        <x-icon name="finance" /><span>Financeiro</span><span class="finance-nav-chevron" aria-hidden="true">⌄</span>
    </summary>
    <div class="finance-submenu" aria-label="Seções do financeiro">
        @foreach (\App\Enums\FinanceSection::cases() as $item)
            <a wire:navigate href="{{ route($item->routeName(), [$workspace, 'month' => $month]) }}"
                x-bind:href="@js(route($item->routeName(), $workspace)) + '?month=' + month"
                @class(['active' => request()->routeIs($item->routeName())])
                @if (request()->routeIs($item->routeName())) aria-current="page" @endif>{{ $item->label() }}</a>
        @endforeach
    </div>
</details>
