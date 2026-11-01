@php
$selectedMonth = \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month);
$navigation = [['label' => 'Ano anterior', 'text' => '« Ano', 'month' => $selectedMonth->subYear()->format('Y-m')], ['label' => 'Mês anterior', 'text' => '‹ Mês', 'month' => $selectedMonth->subMonth()->format('Y-m')], ['label' => 'Próximo mês', 'text' => 'Mês ›', 'month' => $selectedMonth->addMonth()->format('Y-m')], ['label' => 'Próximo ano', 'text' => 'Ano »', 'month' => $selectedMonth->addYear()->format('Y-m')]];
@endphp
<div class="month-navigation">
    <div>
        <span class="eyebrow">MÊS DE REFERÊNCIA</span>
        <h2>{{ ucfirst($selectedMonth->translatedFormat('F \d\e Y')) }}</h2>
        <div class="cycle-dates">
            <span>Ciclo: {{ $period['start']->format('d/m/Y') }} a {{ $period['end']->subDay()->format('d/m/Y') }}</span>
            @can('budget.manage', $workspace)
                <button type="button" class="indicator-pencil" wire:click="startEditingCycle"
                    aria-label="Alterar ciclo financeiro" title="Alterar ciclo financeiro"><x-icon name="pencil" /></button>
            @endcan
        </div>
    </div>
    @if ($editingCycle)
        <form wire:submit="saveCycle" class="cycle-editor">
            <x-cycle-date-fields :month="$month" />
            <div class="actions">
                <button class="button" wire:loading.attr="disabled" wire:target="saveCycle">Salvar ciclo</button>
                <button type="button" class="text-button" wire:click="cancelCycle">Cancelar</button>
            </div>
        </form>
    @endif
    <div class="month-controls">
        @foreach ($navigation as $item)
            <button type="button" class="button secondary month-step" wire:click="openMonth('{{ $item['month'] }}')"
                wire:loading.attr="disabled" aria-label="{{ $item['label'] }}">{{ $item['text'] }}</button>
        @endforeach
    </div>
    @if ($entries->isEmpty())
        @can('finance.create', $workspace)
            @can('budget.manage', $workspace)
                <button type="button" class="button secondary" wire:click="startSynchronization" wire:loading.attr="disabled">
                    Sincronizar com o mês anterior
                </button>
            @endcan
        @endcan
    @endif
    @error('synchronization')
        <p class="field-error" role="alert">{{ $message }}</p>
    @enderror
    <small>Receitas e gastos fixos são independentes por mês. Em um mês sem lançamentos, use a sincronização para
        copiar os fixos do mês anterior como pendentes.</small>
</div>
