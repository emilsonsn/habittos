<dialog class="app-dialog week-entries-dialog" wire:ignore.self aria-labelledby="week-entries-title"
    aria-describedby="week-entries-description" wire:key="week-entries-{{ $month }}-{{ $week['number'] }}"
    x-data x-init="$nextTick(() => $el.showModal())" @cancel.prevent="$wire.closeWeekEntries()"
    @click="if ($event.target === $el) $wire.closeWeekEntries()">
    <div class="dialog-content form-stack">
        <div class="dialog-top">
            <div>
                <span class="eyebrow">GASTOS DA SEMANA · {{ $week['dateLabel'] }}</span>
                <h2 id="week-entries-title">Lançamentos da semana {{ $week['number'] }}</h2>
            </div>
            <button type="button" class="icon-close" wire:click="closeWeekEntries" aria-label="Fechar lançamentos da semana">×</button>
        </div>
        <p id="week-entries-description">Despesas que compõem o valor exibido no gráfico, incluindo pagas e pendentes.</p>
        @if ($week['entries']->isEmpty())
            <div class="empty-state"><p>Nenhum lançamento compõe os gastos desta semana.</p></div>
        @else
            <div class="table-scroll" tabindex="0" role="region" aria-label="Lançamentos da semana {{ $week['number'] }}">
                <table>
                    <thead>
                        <tr><th scope="col">Descrição</th><th scope="col">Data</th><th scope="col">Status</th><th scope="col" class="number">Valor</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($week['entries'] as $weekEntry)
                            <tr wire:key="week-entry-{{ $weekEntry->id }}">
                                <td><strong>{{ $weekEntry->description }}</strong><small>{{ $weekEntry->category?->name ?? 'Sem categoria' }}{{ $weekEntry->is_installment ? ' · Parcelado' : '' }}</small></td>
                                <td>{{ $weekEntry->date->format('d/m') }}</td>
                                <td><span class="tag {{ $weekEntry->paid ? 'settled' : 'pending' }}">{{ $weekEntry->paid ? 'Pago' : 'Pendente' }}</span></td>
                                <td class="number">{{ $money($weekEntry->amount_cents) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <div class="panel-footer week-entries-total"><span>Total da semana</span><strong>{{ $money($week['spent']) }}</strong></div>
        <div class="dialog-actions"><button type="button" class="button secondary" wire:click="closeWeekEntries">Fechar</button></div>
    </div>
</dialog>
