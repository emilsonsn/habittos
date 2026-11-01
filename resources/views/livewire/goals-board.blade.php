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
        <div><span class="eyebrow">{{ ['annual' => 'ANO', 'monthly' => 'MÊS', 'weekly' => 'SEMANA'][$period] }} DE REFERÊNCIA</span>
            <h2>{{ $timeLabel }}</h2>
            @if ($period === 'weekly')<p class="muted">{{ $periodDate->format('d/m/Y') }} a {{ $periodDate->addDays(6)->format('d/m/Y') }}</p>@endif
        </div>
        <div class="actions">
            <button type="button" class="button secondary" wire:click="navigatePeriod(-1)" wire:loading.attr="disabled" aria-label="{{ ['annual' => 'Ano anterior', 'monthly' => 'Mês anterior', 'weekly' => 'Semana anterior'][$period] }}">‹ {{ ['annual' => 'Ano', 'monthly' => 'Mês', 'weekly' => 'Semana'][$period] }}</button>
            <button type="button" class="button secondary" wire:click="openCurrentPeriod" wire:loading.attr="disabled">{{ ['annual' => 'Ano atual', 'monthly' => 'Mês atual', 'weekly' => 'Semana atual'][$period] }}</button>
            <button type="button" class="button secondary" wire:click="navigatePeriod(1)" wire:loading.attr="disabled" aria-label="{{ ['annual' => 'Próximo ano', 'monthly' => 'Próximo mês', 'weekly' => 'Próxima semana'][$period] }}">{{ ['annual' => 'Ano', 'monthly' => 'Mês', 'weekly' => 'Semana'][$period] }} ›</button>
        </div>
    </div>
    <section class="panel goals-workspace" id="goals-results" aria-label="Metas do período">
        <div class="goals-toolbar">
            <div class="goal-display-toggle" role="group" aria-label="Visualização das metas">
                @foreach (['table' => 'Tabela', 'kanban' => 'Kanban'] as $value => $label)
                    <button type="button" wire:click="selectDisplay('{{ $value }}')" aria-pressed="{{ $display === $value ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
            <span class="muted" role="status">{{ $total }} {{ $total === 1 ? 'meta encontrada' : 'metas encontradas' }}</span>
        </div>
        @include('goals.filters')
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
