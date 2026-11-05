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
                <input id="month" type="month" wire:model.live="selectedMonth" required>
                <button type="button" class="button secondary" wire:click="openCurrentMonth" wire:loading.attr="disabled">Mês atual</button>
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
                <strong>{{ $money($available) }}</strong><small>Após despesas, investimentos e reserva</small>
            </div>
            <div class="metric">
                <div class="metric-label">Receitas <span class="green">↙</span></div>
                <strong>{{ $money($income) }}</strong><small>Total recebido e a receber</small>
            </div>
            <div class="metric">
                <div class="metric-label">Despesas Fixas <span class="orange">↗</span></div>
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
                        <button type="button" class="week week-button" wire:click="showWeekEntries({{ $week['number'] }})"
                            aria-label="Ver lançamentos da semana {{ $week['number'] }}, {{ $week['dateLabel'] }}, total {{ $money($week['spent']) }}"
                            aria-haspopup="dialog" wire:loading.attr="disabled" wire:target="showWeekEntries">
                            <span class="week-bar-track" aria-hidden="true">
                                <span class="week-bar {{ $week['limit'] && $week['spent'] > $week['limit'] ? 'over' : '' }}"
                                    style="height: {{ max(3, min(100, ($week['spent'] / max(1, $weeklyLimit, $weeks->max('spent'))) * 100)) }}%">
                                </span>
                            </span><strong>{{ $money($week['spent']) }}</strong><span>Semana
                                {{ $week['number'] }}</span><small>{{ $week['dateLabel'] }}</small>
                        </button>
                    @endforeach
                </div>
                <div class="panel-footer weekly-limits">
                    <span title="Receitas menos despesas fixas, parceladas, despesas fora do gasto semanal e reserva planejada, dividido por 4. Cada despesa é descontada uma única vez.">
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
                    <small class="weekly-limits-note">Base: receitas − despesas fixas, parceladas e fora do gasto semanal − reserva, sem duplicar despesas. O ajustado desconta apenas o excesso das semanas encerradas.</small>
                </div>
            </section>
            @include('finance.planning-indicators')
        </div>
        @include('finance.category-breakdown')
        @include('finance.fixed-expenses')
    @else
        @switch($financeSection)
            @case(\App\Enums\FinanceSection::FixedIncome)
                @include('finance.fixed-entries', ['entryType' => \App\Enums\EntryType::Income->value, 'heading' => 'Receitas fixas', 'total' => $fixedIncome])
                @break
            @case(\App\Enums\FinanceSection::FixedExpenses)
                @include('finance.fixed-entries', ['entryType' => \App\Enums\EntryType::Expense->value, 'heading' => 'Gastos fixos', 'total' => $fixed])
                @break
            @case(\App\Enums\FinanceSection::InstallmentExpenses)
                @include('finance.entries-table', [
                    'entries' => $entries->where('type', \App\Enums\EntryType::Expense->value)->where('is_installment', true),
                    'heading' => 'Despesas parceladas do mês',
                    'emptyTitle' => 'Nenhuma despesa parcelada neste mês.',
                    'emptyDescription' => 'Marque “Parcelado” ao registrar uma despesa para acompanhá-la aqui.',
                ])
                @break
            @case(\App\Enums\FinanceSection::Investments)
                @include('finance.entries-table', [
                    'entries' => $entries->where('type', \App\Enums\EntryType::Investment->value),
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
    @if ($selectedWeekDetails)
        @include('finance.week-entries-dialog', ['week' => $selectedWeekDetails])
    @endif
    @if ($entryFormOpen)
        <dialog class="app-dialog entry-dialog" wire:ignore.self aria-labelledby="entry-dialog-title"
            wire:key="entry-dialog-{{ $entryFormVersion }}" x-data
            x-init="$nextTick(() => $el.showModal())" @cancel.prevent="$wire.closeEntryForm()">
            <livewire:entry-form :workspace-id="$workspace->id" :entry-id="$editingEntryId" :section="$section"
                :key="'entry-form-'.$workspace->id.'-'.$entryFormVersion" />
        </dialog>
    @endif
</div>
