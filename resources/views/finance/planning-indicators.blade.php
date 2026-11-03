<section class="panel planning-indicators">
    <div class="section-heading">
        <div><span class="eyebrow">SEU PLANEJAMENTO</span>
            <h2>Planos para o mês</h2>
        </div>
        <span class="round-icon" aria-hidden="true">◎</span>
    </div>
    @foreach (\App\Enums\FinancialIndicator::cases() as $indicatorType)
        @php
            $indicator = ['key' => $indicatorType->value, 'label' => $indicatorType->label(),
                'description' => $indicatorType->description(),
                'value' => $indicatorType === \App\Enums\FinancialIndicator::MonthlyLimit ? $monthlyLimit : $reserve];
        @endphp
        <div class="planning-indicator" wire:key="planning-{{ $indicator['key'] }}">
            <div class="planning-indicator-heading">
                <span>{{ $indicator['label'] }}</span>
                @can('budget.manage', $workspace)
                    @if ($indicatorType->editableColumn() !== null && $editingIndicator !== $indicator['key'])
                        <button type="button" class="indicator-pencil"
                            wire:click="startEditingIndicator('{{ $indicator['key'] }}')" wire:loading.attr="disabled"
                            aria-label="Editar {{ mb_strtolower($indicator['label']) }}" title="Editar valor">
                            <x-icon name="pencil" />
                        </button>
                    @endif
                @endcan
            </div>
            @if ($editingIndicator === $indicator['key'])
                @include('finance.indicator-editor', ['label' => $indicator['label']])
            @else
                <strong
                    class="planning-indicator-value {{ $indicator['value'] === null ? 'not-set' : '' }}">{{ $indicator['value'] !== null ? $money($indicator['value']) : 'Não definido' }}</strong>
            @endif
            <small>{{ $indicator['description'] }}</small>
        </div>
    @endforeach
</section>
