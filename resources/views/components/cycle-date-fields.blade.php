@props(['month'])
@php($firstDay = \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month))
<label>Início do ciclo
    <input type="date" wire:model="cycleStartInput" required
        min="{{ $firstDay->toDateString() }}" max="{{ $firstDay->endOfMonth()->toDateString() }}">
    @error('cycleStartInput')<span class="field-error">{{ $message }}</span>@enderror
</label>
<label>Fim do ciclo (inclusive)
    <input type="date" wire:model="cycleEndInput" required
        min="{{ $firstDay->endOfMonth()->toDateString() }}" max="{{ $firstDay->addMonth()->endOfMonth()->subDay()->toDateString() }}">
    @error('cycleEndInput')<span class="field-error">{{ $message }}</span>@enderror
</label>
<small>O início pertence ao mês selecionado. O fim pode ser o último dia desse mês ou uma data no mês seguinte,
    deixando ao menos um dia para o próximo ciclo. O ciclo anterior termina na véspera do início e o próximo começa
    no dia seguinte ao fim informado.</small>
