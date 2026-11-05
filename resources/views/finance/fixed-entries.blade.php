<section class="panel fixed-cashflow {{ $entryType === \App\Enums\EntryType::Income->value ? 'fixed-income' : 'fixed-outgoing' }}">
    <div class="section-heading">
        <div>
            <span class="eyebrow">{{ $entryType === \App\Enums\EntryType::Income->value ? 'ENTRADAS DO MÊS' : 'COMPROMISSOS DO MÊS' }}</span>
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
                <div><strong>{{ $fixedEntry->description }}</strong><small>{{ $entryType === \App\Enums\EntryType::Income->value ? 'Recebimento' : 'Vencimento' }}
                        {{ $fixedEntry->date->format('d/m') }}</small></div>
                <strong>{{ $money($fixedEntry->amount_cents) }}</strong>
                @can('finance.update', $workspace)
                    <button type="button" class="tag status-toggle {{ $fixedEntry->paid ? 'settled' : 'pending' }}"
                        wire:click="togglePaid({{ $fixedEntry->id }})" wire:loading.attr="disabled"
                        title="Alternar status">
                        {{ $fixedEntry->paid ? ($entryType === \App\Enums\EntryType::Income->value ? 'Recebido' : 'Pago') : 'Pendente' }}
                    </button>
                    <button type="button" class="text-button" wire:click="editEntry({{ $fixedEntry->id }})" wire:loading.attr="disabled">Editar</button>
                @else
                    <span
                        class="tag {{ $fixedEntry->paid ? 'settled' : 'pending' }}">{{ $fixedEntry->paid ? ($entryType === \App\Enums\EntryType::Income->value ? 'Recebido' : 'Pago') : 'Pendente' }}</span>
                @endcan
                @can('finance.delete', $workspace)
                    <button type="button" class="text-button danger"
                        wire:click="deleteEntry({{ $fixedEntry->id }})"
                        data-confirm="Este lançamento será excluído definitivamente e os saldos e resumos serão recalculados." data-confirm-title="Excluir lançamento?" data-confirm-label="Excluir lançamento" data-confirm-subject="{{ $fixedEntry->description }}"
                        wire:loading.attr="disabled">Excluir</button>
                @endcan
            </div>
        @empty
            <div class="fixed-empty"><span aria-hidden="true">{{ $entryType === \App\Enums\EntryType::Income->value ? '↙' : '↗' }}</span>
                <p>{{ $entryType === \App\Enums\EntryType::Income->value ? 'Nenhuma receita fixa neste mês.' : 'Nenhum gasto fixo neste mês.' }}
                </p><small>Marque “Lançamento fixo mensal” ao registrar
                    {{ $entryType === \App\Enums\EntryType::Income->value ? 'uma receita, como seu salário.' : 'uma despesa.' }}</small>
            </div>
        @endforelse
    </div>
</section>
