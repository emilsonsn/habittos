<div class="goals-board">
    @if ($message)
        <div class="notice success" role="status">{{ $message }}</div>
    @endif
    <div class="page-heading">
        <div><span class="eyebrow">DO PLANO À PRÁTICA</span>
            <h1>{{ \App\Enums\GoalSection::from($period)->label() }}<span class="heading-dot">.</span></h1>
            <p>O que você quer realizar? Organize seus planos no seu ritmo.</p>
        </div>
        @can('goals.manage', $workspace)
            <button type="button" class="button" wire:click="openCreate" wire:loading.attr="disabled">+ Nova meta</button>
        @endcan
    </div>
    <div class="goal-time-navigation">
        <div><span class="eyebrow">{{ mb_strtoupper(\App\Enums\GoalPeriod::from($period)->unit()) }} DE REFERÊNCIA</span>
            <h2>{{ $timeLabel }}</h2>
            @if ($period === \App\Enums\GoalPeriod::Weekly->value)<p class="muted">{{ $periodDate->format('d/m/Y') }} a {{ $periodDate->addDays(6)->format('d/m/Y') }}</p>@endif
        </div>
        <div class="actions">
            <button type="button" class="button secondary" wire:click="navigatePeriod(-1)" wire:loading.attr="disabled" aria-label="{{ \App\Enums\GoalPeriod::from($period)->previousLabel() }}">‹ {{ \App\Enums\GoalPeriod::from($period)->unit() }}</button>
            <button type="button" class="button secondary" wire:click="openCurrentPeriod" wire:loading.attr="disabled">{{ \App\Enums\GoalPeriod::from($period)->currentLabel() }}</button>
            <button type="button" class="button secondary" wire:click="navigatePeriod(1)" wire:loading.attr="disabled" aria-label="{{ \App\Enums\GoalPeriod::from($period)->nextLabel() }}">{{ \App\Enums\GoalPeriod::from($period)->unit() }} ›</button>
        </div>
    </div>
    @php
        $activeFilterCount = collect([$search, $statusFilter, $priorityFilter, $categoryFilter, $dateFrom, $dateTo])->filter(fn ($value) => trim($value) !== '')->count();
    @endphp
    <section class="panel goals-workspace" id="goals-results" aria-label="Metas do período" x-data="{ filtersOpen: false }">
        <div class="goals-toolbar">
            <div class="goal-display-toggle" role="group" aria-label="Visualização das metas">
                @foreach (\App\Enums\GoalDisplay::options() as $value => $label)
                    <button type="button" wire:click="selectDisplay('{{ $value }}')" aria-pressed="{{ $display === $value ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
            <div class="goal-filter-actions">
                <span class="muted" role="status">{{ $total }} {{ $total === 1 ? 'meta encontrada' : 'metas encontradas' }}</span>
                <button type="button" class="button secondary goal-filters-toggle" @click="filtersOpen = !filtersOpen"
                    :aria-expanded="filtersOpen" aria-expanded="false" aria-controls="goal-filters-panel">
                    <span x-text="filtersOpen ? 'Ocultar filtros' : 'Mostrar filtros'">Mostrar filtros</span>
                    @if ($activeFilterCount)<span class="count">{{ $activeFilterCount }} {{ $activeFilterCount === 1 ? 'ativo' : 'ativos' }}</span>@endif
                </button>
            </div>
        </div>
        <div id="goal-filters-panel" x-cloak x-show="filtersOpen">
            @include('goals.filters')
        </div>
        @if ($errors->has('dateFrom') || $errors->has('dateTo'))
            <p class="field-error" role="alert" x-show="!filtersOpen">Revise o intervalo de prazo nos filtros.</p>
        @endif
        @if ($total === 0)
            <div class="empty-state">
                <span>◎</span><h2>Nenhuma meta encontrada.</h2>
                <p>Experimente outros filtros ou comece um novo plano para este período.</p>
                @can('goals.manage', $workspace)
                    <button type="button" class="button secondary" wire:click="openCreate">Criar uma meta</button>
                @endcan
            </div>
        @elseif ($display === 'table')
            @include('goals.table')
        @else
            @include('goals.kanban')
        @endif
    </section>
    @if ($formOpen)
        <dialog class="app-dialog goal-dialog" wire:ignore.self wire:key="goal-dialog-{{ $formVersion }}"
            aria-labelledby="goal-dialog-title" x-data x-init="$nextTick(() => $el.showModal())" @cancel.prevent="$wire.closeForm()">
            @include('goals.form')
        </dialog>
    @endif
</div>
