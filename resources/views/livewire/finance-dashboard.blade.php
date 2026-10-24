<div>
    @if ($message)
        <div class="notice success" role="status">{{ $message }}</div>
    @endif
    @php($money = fn($value) => \App\Services\Money::format((int) $value))
    <div class="page-heading">
        <div><span class="eyebrow">SEU DINHEIRO, COM DIREÇÃO</span>
            <h1>{{ $financeSection === \App\Enums\FinanceSection::Dashboard ? 'Visão financeira' : $financeSection->label() }}<span class="heading-dot">.</span></h1>
            <p>{{ $financeSection === \App\Enums\FinanceSection::Dashboard ? 'Entenda seu mês e planeje os próximos passos.' : 'Acompanhe os registros do mês selecionado.' }}</p>
        </div>
        <div class="actions">
            <form wire:submit="openMonth" class="month-filter">
                <label class="sr-only" for="month">Mês de referência</label>
                <input id="month" type="month" wire:model="selectedMonth" required>
                <button class="button secondary" wire:loading.attr="disabled">Ver mês</button>
            </form>
            @can('finance.create', $workspace)
                <button type="button" class="button" wire:click="createEntry" wire:loading.attr="disabled">+ Novo lançamento</button>
            @endcan
        </div>
    </div>
    @error('month')
        <p class="field-error" role="alert">{{ $message }}</p>
    @enderror
    @include('finance.month-navigation')
    @include('finance.synchronize-month')
    @if ($financeSection === \App\Enums\FinanceSection::Dashboard)
        <div class="metrics">
            <div class="metric balance">
                <div class="metric-label">Saldo disponível <span>↗</span></div>
                <strong>{{ $money($available) }}</strong><small>Após pagamentos e reserva separada</small>
            </div>
            <div class="metric">
                <div class="metric-label">Receitas <span class="green">↙</span></div>
                <strong>{{ $money($income) }}</strong><small>Total recebido e a receber</small>
            </div>
            <div class="metric">
                <div class="metric-label">Despesas <span class="orange">↗</span></div>
                <strong>{{ $money($expenses) }}</strong><small>{{ $money($fixed) }} em compromissos fixos</small>
            </div>
            <div class="metric">
                <div class="metric-label">Investimentos <span class="purple">◇</span></div>
                <strong>{{ $money($investmentBalance) }}</strong>
                <small>Saldo total · Movimentação no ciclo: {{ $money($invested) }}</small>
            </div>
        </div>
        <div class="dashboard-grid">
            <section class="panel">
                <div class="section-heading">
                    <div><span class="eyebrow">UM POUCO POR VEZ</span>
                        <h2>Seu ritmo na semana</h2>
                    </div><span class="tag">Despesas variáveis</span>
                </div>
                <div class="weekly-chart">
                    @foreach ($weeks as $week)
                        <div class="week">
                            <div class="week-bar-track">
                                <div class="week-bar {{ $week['limit'] && $week['spent'] > $week['limit'] ? 'over' : '' }}"
                                    style="height: {{ max(3, min(100, ($week['spent'] / max(1, $weeklyLimit, $weeks->max('spent'))) * 100)) }}%">
                                </div>
                            </div><strong>{{ $money($week['spent']) }}</strong><span>Semana
                                {{ $week['number'] }}</span><small>{{ $week['dateLabel'] }}</small>
                        </div>
                    @endforeach
                </div>
                <div class="panel-footer weekly-limits">
                    <span title="Todas as receitas menos despesas fixas e reserva planejada, dividido por 4.">
                        Limite por semana <strong>{{ $money($weeklyLimit) }}</strong>
                    </span>
                    <span title="Limite semanal menos o excesso das semanas encerradas, dividido pelas semanas restantes, incluindo a atual.">
                        Limite ajustado
                        <strong>{{ $weeklyPlan['adjustedLimit'] !== null ? $money($weeklyPlan['adjustedLimit']) : 'Mês encerrado' }}</strong>
                        @if ($weeklyPlan['remainingWeeks'] > 0)
                            <small>({{ $weeklyPlan['remainingWeeks'] }} {{ $weeklyPlan['remainingWeeks'] === 1 ? 'semana restante' : 'semanas restantes' }})</small>
                        @endif
                    </span>
                    @if ($weeklyPlan['currentWeekRemaining'] !== null)
                        <span class="current-week-remaining" title="Limite ajustado menos as despesas variáveis da semana vigente.">
                            Restante nesta semana
                            <strong @class(['negative' => $weeklyPlan['currentWeekRemaining'] < 0])>{{ $money($weeklyPlan['currentWeekRemaining']) }}</strong>
                        </span>
                    @endif
                    <small class="weekly-limits-note">Base: receitas − fixos − reserva. O ajustado desconta apenas o excesso das semanas encerradas.</small>
                </div>
            </section>
            @include('finance.planning-indicators')
        </div>
        @include('finance.fixed-expenses')
        @include('finance.category-breakdown')
    @else
        @switch($financeSection)
            @case(\App\Enums\FinanceSection::FixedIncome)
                @include('finance.fixed-entries', ['entryType' => 'income', 'heading' => 'Receitas fixas', 'total' => $fixedIncome])
                @break
            @case(\App\Enums\FinanceSection::FixedExpenses)
                @include('finance.fixed-entries', ['entryType' => 'expense', 'heading' => 'Gastos fixos', 'total' => $fixed])
                @break
            @case(\App\Enums\FinanceSection::Investments)
                @include('finance.entries-table', [
                    'entries' => $entries->where('type', 'investment'),
                    'heading' => 'Investimentos do mês',
                    'emptyTitle' => 'Nenhum investimento neste mês.',
                    'emptyDescription' => 'Registre seus aportes para acompanhar os investimentos do ciclo selecionado.',
                ])
                @break
            @case(\App\Enums\FinanceSection::Entries)
                @include('finance.entries-table')
                @break
        @endswitch
    @endif
    @if ($entryFormOpen)
        <dialog class="app-dialog entry-dialog" wire:ignore.self aria-labelledby="entry-dialog-title"
            wire:key="entry-dialog-{{ $entryFormVersion }}" x-data
            x-init="$nextTick(() => $el.showModal())" @cancel.prevent="$wire.closeEntryForm()">
            <livewire:entry-form :workspace-id="$workspace->id" :entry-id="$editingEntryId"
                :key="'entry-form-'.$workspace->id.'-'.$entryFormVersion" />
        </dialog>
    @endif
</div>
