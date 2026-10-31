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
        <p class="muted entry-balance-note">Saldo acumulado desde o início do ciclo: receitas menos despesas, incluindo pendentes. Investimentos não alteram o saldo. O filtro de categorias preserva o saldo completo. Mais recentes primeiro.</p>
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
                                @else
                                    <small>{{ $entry->category?->name ?? 'Sem categoria' }}{{ $entry->is_fixed ? ' · Fixo' : '' }}</small>
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
                            <td class="number {{ $entry->amount_cents < 0 ? 'negative' : ($entry->type !== 'expense' ? 'green' : '') }}">
                                {{ $entry->type === 'expense' || $entry->amount_cents < 0 ? '−' : '+' }}
                                {{ $money(abs($entry->amount_cents)) }}<small>{{ ['income' => 'Receita', 'expense' => 'Despesa', 'investment' => 'Investimento'][$entry->type] }}</small>
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
