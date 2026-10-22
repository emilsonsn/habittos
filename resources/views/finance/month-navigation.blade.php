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
    </div>
    <div class="month-controls">
        @foreach ($navigation as $item)
            <button type="button" class="button secondary month-step" wire:click="openMonth('{{ $item['month'] }}')"
                wire:loading.attr="disabled" aria-label="{{ $item['label'] }}">{{ $item['text'] }}</button>
        @endforeach
    </div>
    <small>Fixos separados por mês. Ao abrir um mês pela primeira vez, os fixos do mês anterior são copiados como
        pendentes.</small>
</div>
