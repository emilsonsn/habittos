@php
$selectedMonth = \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month);
$canOpenMonth = auth()
    ->user()
    ->can('finance.create', $workspace);
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
                    aria-label="Alterar início do mês financeiro" title="Alterar início do mês financeiro"><x-icon name="pencil" /></button>
            @endcan
        </div>
    </div>
    @if ($editingCycle)
        <form wire:submit="saveCycle" class="cycle-editor">
            <label for="cycle-start">Início de {{ $selectedMonth->translatedFormat('F') }}
                <input type="date" id="cycle-start" wire:model="cycleStartInput" required
                    min="{{ $selectedMonth->toDateString() }}" max="{{ $selectedMonth->endOfMonth()->toDateString() }}"
                    wire:keydown.escape="cancelCycle">
            </label>
            <div class="actions">
                <button class="button" wire:loading.attr="disabled" wire:target="saveCycle">Salvar início</button>
                <button type="button" class="text-button" wire:click="cancelCycle">Cancelar</button>
            </div>
            <small>Escolha a data de início deste mês. O padrão é dia 01. O ciclo termina na véspera do início do próximo mês, que também pode ser alterado ao navegar até ele. Isso altera os períodos exibidos, sem mudar as datas dos lançamentos.</small>
            @error('cycleStartInput')
                <p class="field-error" role="alert">{{ $message }}</p>
            @enderror
        </form>
    @endif
    <div class="month-controls">
        @foreach ($navigation as $item)
            <button type="button" class="button secondary month-step" wire:click="openMonth('{{ $item['month'] }}')"
                wire:loading.attr="disabled" aria-label="{{ $item['label'] }}">{{ $item['text'] }}</button>
        @endforeach
    </div>
    <small>Fixos separados por mês. Ao abrir um mês pela primeira vez, as receitas e despesas fixas do mês anterior são
        copiadas como
        pendentes.</small>
</div>
