<section class="panel fixed-cashflow {{ $entryType === 'income' ? 'fixed-income' : 'fixed-outgoing' }}">
    <div class="section-heading">
        <div>
            <span class="eyebrow">{{ $entryType === 'income' ? 'ENTRADAS DO MÊS' : 'COMPROMISSOS DO MÊS' }}</span>
            <h2>{{ $heading }} <span
                    class="count">{{ $entries->where('type', $entryType)->where('is_fixed', true)->count() }}</span>
            </h2>
        </div>
        <strong class="fixed-cashflow-total">{{ $money($total) }}</strong>
    </div>
    <p class="muted">As alterações ficam neste mês. Os outros meses mantêm seus próprios registros.</p>
    <div class="fixed-expense-list" tabindex="0" role="region" aria-label="Lista de {{ mb_strtolower($heading) }}">
        @forelse ($entries->where('type', $entryType)->where('is_fixed', true) as $fixedEntry)
            <div class="fixed-expense" wire:key="fixed-{{ $fixedEntry->id }}">
                <div><strong>{{ $fixedEntry->description }}</strong><small>{{ $entryType === 'income' ? 'Recebimento' : 'Vencimento' }}
                        {{ $fixedEntry->date->format('d/m') }}</small></div>
                <strong>{{ $money($fixedEntry->amount_cents) }}</strong>
                @can('finance.update', $workspace)
                    <button type="button" class="tag status-toggle {{ $fixedEntry->paid ? 'settled' : 'pending' }}"
                        wire:click="togglePaid({{ $fixedEntry->id }})" wire:loading.attr="disabled"
                        title="Alternar status">
                        {{ $fixedEntry->paid ? ($entryType === 'income' ? 'Recebido' : 'Pago') : 'Pendente' }}
                    </button>
                    <a wire:navigate href="{{ route('entries.edit', [$workspace, $fixedEntry]) }}">Editar</a>
                @else
                    <span
                        class="tag {{ $fixedEntry->paid ? 'settled' : 'pending' }}">{{ $fixedEntry->paid ? ($entryType === 'income' ? 'Recebido' : 'Pago') : 'Pendente' }}</span>
                @endcan
            </div>
        @empty
            <div class="fixed-empty"><span aria-hidden="true">{{ $entryType === 'income' ? '↙' : '↗' }}</span>
                <p>{{ $entryType === 'income' ? 'Nenhuma receita fixa neste mês.' : 'Nenhum gasto fixo neste mês.' }}
                </p><small>Marque “Lançamento fixo mensal” ao registrar
                    {{ $entryType === 'income' ? 'uma receita, como seu salário.' : 'uma despesa.' }}</small>
            </div>
        @endforelse
    </div>
</section>
