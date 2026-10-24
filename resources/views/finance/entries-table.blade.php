<section class="panel transactions">
    <div class="section-heading">
        <div><span class="eyebrow">CADA MOVIMENTO CONTA</span>
            <h2>Lançamentos do mês <span class="count">{{ $entries->count() }}</span></h2>
        </div>
    </div>
    @if ($entries->isEmpty())
        <div class="empty-state"><span>↗</span>
            <h3>Um primeiro passo para ter clareza.</h3>
            <p>Registre sua renda e suas despesas para acompanhar o mês.</p>
            @can('finance.create', $workspace)
                <a wire:navigate class="button secondary"
                    href="{{ route('entries.create', [$workspace, 'month' => $month]) }}">Criar
                    primeiro
                    lançamento</a>
            @endcan
        </div>
    @else
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Data</th>
                        <th>Status</th>
                        <th class="number">Valor</th>
                        <th><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr wire:key="entry-{{ $entry->id }}">
                            <td><strong>{{ $entry->description }}</strong><small>{{ $entry->category?->name ?? 'Sem categoria' }}{{ $entry->is_fixed ? ' · Fixo' : '' }}</small>
                            </td>
                            <td>{{ $entry->date->format('d/m') }}</td>
                            <td>
                                @can('finance.update', $workspace)
                                    <button type="button"
                                        class="tag status-toggle {{ $entry->paid ? 'settled' : 'pending' }}"
                                        wire:click="togglePaid({{ $entry->id }})" wire:loading.attr="disabled"
                                        title="Alternar status">{{ $entry->paid ? 'Efetivado' : 'Pendente' }}</button>
                                @else
                                    <span
                                        class="tag {{ $entry->paid ? 'settled' : 'pending' }}">{{ $entry->paid ? 'Efetivado' : 'Pendente' }}</span>
                                @endcan
                            </td>
                            <td class="number {{ $entry->type === 'income' ? 'green' : '' }}">
                                {{ $entry->type === 'income' ? '+' : '−' }}
                                {{ $money($entry->amount_cents) }}<small>{{ ['income' => 'Receita', 'expense' => 'Despesa', 'investment' => 'Investimento'][$entry->type] }}</small>
                            </td>
                            <td>
                                <div class="row-actions">
                                    @can('finance.update', $workspace)
                                        <a wire:navigate
                                            href="{{ route('entries.edit', [$workspace, $entry]) }}">Editar</a>
                                        @endcan @can('finance.delete', $workspace)
                                        <button type="button" class="text-button danger"
                                            wire:click="deleteEntry({{ $entry->id }})"
                                            wire:confirm="Excluir este lançamento?"
                                            wire:loading.attr="disabled">Excluir</button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
