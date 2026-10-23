<div>
    @if ($message)
        <div class="notice success" role="status">{{ $message }}</div>
    @endif
    @php($money = fn($value) => \App\Services\Money::format((int) $value))
    <div class="page-heading">
        <div><span class="eyebrow">SEU DINHEIRO, COM DIREÇÃO</span>
            <h1>Visão financeira<span class="heading-dot">.</span></h1>
            <p>Entenda seu mês e planeje os próximos passos.</p>
        </div>
        <div class="actions">
            <form wire:submit="openMonth" class="month-filter">
                <label class="sr-only" for="month">Mês de referência</label>
                <input id="month" type="month" wire:model="selectedMonth" required>
                <button class="button secondary" wire:loading.attr="disabled">Ver mês</button>
            </form>
            @can('finance.create', $workspace)
                <a wire:navigate class="button" href="{{ route('entries.create', [$workspace, 'month' => $month]) }}">+ Novo
                    lançamento</a>
            @endcan
        </div>
    </div>
    @error('month')
        <p class="field-error" role="alert">{{ $message }}</p>
    @enderror
    @include('finance.month-navigation')
    <div class="metrics">
        <div class="metric balance">
            <div class="metric-label">Saldo realizado <span>↗</span></div>
            <strong>{{ $money($balance) }}</strong><small>Entradas e saídas efetivadas no mês</small>
        </div>
        <div class="metric">
            <div class="metric-label">Receitas <span class="green">↙</span></div>
            <strong>{{ $money($income) }}</strong><small>Total recebido e a receber</small>
        </div>
        <div class="metric">
            <div class="metric-label">Despesas <span class="orange">↗</span></div>
            <strong>{{ $money($expenses) }}</strong><small>{{ $money($fixed) }} em compromissos fixos</small>
        </div>
        <div class="metric">
            <div class="metric-label">Investimentos <span class="purple">◇</span></div>
            <strong>{{ $money($investmentBalance ?? $invested) }}</strong>
            <small>{{ $investmentBalance !== null ? 'Saldo acumulado · Aportes no mês: ' . $money($invested) : 'Aportes registrados no mês' }}</small>
        </div>
    </div>
    <div class="dashboard-grid">
        <section class="panel">
            <div class="section-heading">
                <div><span class="eyebrow">UM POUCO POR VEZ</span>
                    <h2>Seu ritmo na semana</h2>
                </div><span class="tag">Despesas variáveis</span>
            </div>
            <div class="weekly-chart">
                @foreach ($weeks as $week)
                    <div class="week">
                        <div class="week-bar-track">
                            <div class="week-bar {{ $week['limit'] && $week['spent'] > $week['limit'] ? 'over' : '' }}"
                                style="height: {{ max(3, min(100, ($week['spent'] / max(1, $weeklyLimit, $weeks->max('spent'))) * 100)) }}%">
                            </div>
                        </div><strong>{{ $money($week['spent']) }}</strong><span>Semana
                            {{ $week['number'] }}</span><small>{{ ($week['number'] - 1) * 7 + 1 }}–{{ min($week['number'] * 7, \Carbon\Carbon::parse($month . '-01')->daysInMonth) }}</small>
                    </div>
                @endforeach
            </div>
            <div class="panel-footer">Limite por semana <strong>{{ $money($weeklyLimit) }}</strong><span
                    class="muted">Inclui despesas pendentes; exclui fixos.</span></div>
        </section>
        <section class="panel">
            <div class="section-heading">
                <div><span class="eyebrow">OLHANDO PARA FRENTE</span>
                    <h2>Como o mês termina?</h2>
                </div><span class="round-icon">◎</span>
            </div>
            <div class="projection {{ $projected < 0 ? 'negative' : '' }}">{{ $money($projected) }}</div>
            <p class="muted">Saldo projetado após despesas, investimentos e reserva planejada.</p>
            <div class="summary-row"><span>Reserva planejada</span><strong>{{ $money($reserve) }}</strong></div>
            <div class="summary-row"><span>Saídas ainda pendentes</span><strong>{{ $money($pending) }}</strong></div>
            @can('budget.manage', $workspace)
                <details class="budget-details">
                    <summary>Ajustar planejamento</summary>
                    <form wire:submit="saveBudget" class="form-stack">
                        <label>Limite semanal (R$)<input type="number" wire:model="weeklyLimitInput" min="0"
                                step="0.01" required></label>
                        @error('weeklyLimitInput')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                        <label>Reserva planejada (R$)<input type="number" wire:model="reserveInput" min="0"
                                step="0.01" required></label>
                        @error('reserveInput')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                        <button class="button" wire:loading.attr="disabled">Salvar planejamento</button>
                    </form>
                </details>
            @endcan
        </section>
    </div>
    @include('finance.fixed-expenses')
    <div class="dashboard-grid lower-grid">
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
        <section class="panel">
            <div class="section-heading">
                <div><span class="eyebrow">PARA ONDE VAI</span>
                    <h2>Despesas por categoria</h2>
                </div>
            </div>
            @forelse($byCategory as $name => $total)
                <div class="category-item">
                    <div><span>{{ $name }}</span><strong>{{ $money($total) }}</strong></div><progress
                        value="{{ $total }}" max="{{ max(1, $expenses) }}"
                        aria-label="{{ $name }}"></progress><small>{{ number_format(($total / max(1, $expenses)) * 100, 1, ',', '.') }}%
                        das despesas</small>
            </div>@empty<p class="muted">As categorias aparecem conforme você registra despesas.</p>
            @endforelse
            @can('budget.manage', $workspace)
                <details>
                    <summary>+ Nova categoria</summary>
                    <form wire:submit="saveCategory" class="form-stack">
                        <label>Nome<input wire:model="categoryName" maxlength="80" required></label>
                        @error('categoryName')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                        <button class="button secondary" wire:loading.attr="disabled">Adicionar categoria</button>
                    </form>
                </details>
            @endcan
        </section>
    </div>

</div>
