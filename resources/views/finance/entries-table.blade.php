@php($showCategory = $financeSection === \App\Enums\FinanceSection::Entries)
<section class="panel transactions">
    <div class="section-heading">
        <div><span class="eyebrow">CADA MOVIMENTO CONTA</span>
            <h2>{{ $heading ?? 'Lançamentos do mês' }} <span class="count">{{ $entries->count() }}</span></h2>
        </div>
        @if ($showCategory)
            <details class="entry-category-filter">
                <summary>Categorias <span>{{ count($categoryFilter) ? count($categoryFilter).' '.(count($categoryFilter) === 1 ? 'selecionada' : 'selecionadas') : 'Todas as categorias' }}</span></summary>
                <div class="entry-category-options">
                    <fieldset>
                        <legend class="sr-only">Filtrar por categorias</legend>
                        <label class="checkbox"><input type="checkbox" wire:model.live="categoryFilter" value="uncategorized">Sem categoria</label>
                        @foreach ($categories as $category)
                            <label class="checkbox" wire:key="category-filter-{{ $category->id }}"><input type="checkbox" wire:model.live="categoryFilter" value="{{ $category->id }}">{{ $category->name }}</label>
                        @endforeach
                    </fieldset>
                    @if ($categoryFilter !== [])
                        <button type="button" class="text-button" wire:click="$set('categoryFilter', [])">Limpar filtro</button>
                    @endif
                </div>
            </details>
        @endif
    </div>
    @if ($showCategory)
        <p class="muted entry-balance-note">Saldo acumulado desde o início do ciclo, incluindo pendentes: receitas e retiradas de investimentos aumentam o saldo; despesas e aportes reduzem. O filtro de categorias preserva o saldo completo. Mais recentes primeiro.</p>
    @endif
    @if ($entries->isEmpty())
        <div class="empty-state"><span>↗</span>
            @if ($showCategory && $categoryFilter !== [])
                <h3>Nenhum lançamento nas categorias selecionadas neste mês.</h3>
                <p>Escolha outras categorias ou volte a exibir todos os lançamentos.</p>
                <button type="button" class="button secondary" wire:click="$set('categoryFilter', [])">Limpar filtro</button>
            @else
                <h3>{{ $emptyTitle ?? 'Um primeiro passo para ter clareza.' }}</h3>
                <p>{{ $emptyDescription ?? 'Registre sua renda e suas despesas para acompanhar o mês.' }}</p>
                @can('finance.create', $workspace)
                <button type="button" class="button secondary" wire:click="createEntry" wire:loading.attr="disabled">Criar primeiro lançamento</button>
                @endcan
            @endif
        </div>
    @else
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        @if ($showCategory)
                            <th scope="col">Categoria</th>
                        @endif
                        <th>Data</th>
                        <th>Status</th>
                        <th class="number">Valor</th>
                        @if ($showCategory)
                            <th scope="col" class="number">Saldo</th>
                        @endif
                        <th><span class="sr-only">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr wire:key="entry-{{ $entry->id }}">
                            <td><strong>{{ $entry->description }}</strong>
                                @if ($showCategory)
                                    @if ($entry->is_fixed)<small>Fixo</small>@endif
                                    @if ($entry->is_installment)<small>Parcelado</small>@endif
                                @else
                                    <small>{{ $entry->category?->name ?? 'Sem categoria' }}{{ $entry->is_fixed ? ' · Fixo' : '' }}{{ $entry->is_installment ? ' · Parcelado' : '' }}</small>
                                @endif
                            </td>
                            @if ($showCategory)
                                <td>{{ $entry->category?->name ?? 'Sem categoria' }}</td>
                            @endif
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
                            @php($displayAmount = $showCategory && $entry->type === \App\Enums\EntryType::Investment->value ? -$entry->amount_cents : $entry->amount_cents)
                            @php($isOutflow = $entry->type === \App\Enums\EntryType::Expense->value || $displayAmount < 0)
                            <td class="number {{ $isOutflow ? 'negative' : 'green' }}">
                                {{ $isOutflow ? '−' : '+' }}
                                {{ $money(abs($entry->amount_cents)) }}<small>{{ \App\Enums\EntryType::from($entry->type)->label() }}</small>
                            </td>
                            @if ($showCategory)
                                <td class="number {{ $entryBalances[$entry->id]['after'] < 0 ? 'negative' : '' }}">
                                    <strong>{{ $money($entryBalances[$entry->id]['after']) }}</strong>
                                    <small>Antes: {{ $money($entryBalances[$entry->id]['before']) }}</small>
                                </td>
                            @endif
                            <td>
                                <div class="row-actions">
                                    @can('finance.update', $workspace)
                                        <button type="button" class="text-button" wire:click="editEntry({{ $entry->id }})" wire:loading.attr="disabled">Editar</button>
                                        @endcan @can('finance.delete', $workspace)
                                        <button type="button" class="text-button danger"
                                            wire:click="deleteEntry({{ $entry->id }})"
                                            data-confirm="Este lançamento será excluído definitivamente e os saldos e resumos serão recalculados." data-confirm-title="Excluir lançamento?" data-confirm-label="Excluir lançamento" data-confirm-subject="{{ $entry->description }}"
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
