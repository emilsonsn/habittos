<div>
    @if ($message)
        <div class="notice success" role="status">{{ $message }}</div>
    @endif
    @php($money = fn($value) => \App\Services\Money::format((int) $value))
    <div class="page-heading">
        <div><span class="eyebrow">DÊ UM DESTINO AOS SEUS PLANOS</span>
            <h1>Metas que movem você<span class="heading-dot">.</span></h1>
            <p>Objetivos financeiros anuais e mensais, um passo de cada vez.</p>
        </div>
    </div>
    <div class="dashboard-grid">
        <section>
            <div class="goals-list">
                @forelse($goals as $goal)
                    <article class="panel" wire:key="goal-{{ $goal->id }}"><span
                            class="tag">{{ $goal->period === 'annual' ? 'Anual · ' . $goal->year : 'Mensal · ' . str_pad($goal->month, 2, '0', STR_PAD_LEFT) . '/' . $goal->year }}</span>
                        <h2>{{ $goal->title }}</h2>
                        <div class="goal-progress"><strong>{{ $money($goal->current_cents) }}</strong><span
                                class="muted">de
                                {{ $money($goal->target_cents) }}</span></div><progress
                            value="{{ min($goal->current_cents, $goal->target_cents) }}"
                            max="{{ $goal->target_cents }}"></progress>
                        <p class="muted">
                            {{ number_format(($goal->current_cents / $goal->target_cents) * 100, 1, ',', '.') }}%
                            alcançado
                            ·
                            Progresso informado manualmente</p>
                        @can('goals.manage', $workspace)
                            <form wire:submit="updateProgress({{ $goal->id }})" class="inline-form">
                                <label>Valor alcançado (R$)<x-money-input model="progress.{{ $goal->id }}"
                                        required />id }}"
                                    min="0" step="0.01" placeholder="{{ $goal->current_cents / 100 }}"
                                    required></label><button class="button secondary"
                                    wire:loading.attr="disabled">Atualizar</button>
                            </form>
                            @error('progress.' . $goal->id)
                                <p class="field-error" role="alert">{{ $message }}</p>
                            @enderror
                            <button type="button" class="text-button danger" wire:click="deleteGoal({{ $goal->id }})"
                                wire:confirm="Excluir esta meta?" wire:loading.attr="disabled">Excluir meta</button>
                        @endcan
                    </article>
                @empty<div class="panel empty-state"><span>◎</span>
                        <h2>Qual é o próximo plano?</h2>
                        <p>Uma reserva de emergência, uma viagem ou um novo começo.</p>
                    </div>
                @endforelse
            </div>
        </section>
        @can('goals.manage', $workspace)<section class="panel"><span class="eyebrow">DO PLANO À PRÁTICA</span>
                <h2>Criar meta financeira</h2>
                <form wire:submit="createGoal" class="form-stack">
                    <label>O que você quer realizar?<input wire:model="title" maxlength="150" required></label>
                    @error('title')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                    <label>Período<select wire:model.live="period">
                            <option value="annual">Anual</option>
                            <option value="monthly">Mensal</option>
                        </select></label>
                    <div class="form-grid"><label>Ano<input type="number" wire:model="year" min="2000" max="2100"
                                required></label>
                        @if ($period === 'monthly')
                            <label>Mês<select wire:model="month" required>
                                    <option value="">Selecione</option>
                                    @for ($i = 1; $i <= 12; $i++)
                                        <option value="{{ $i }}">{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}
                                        </option>
                                    @endfor
                                </select></label>
                        @endif
                    </div>
                    @error('month')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                    @error('year')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                    <label>Valor desejado (R$)<x-money-input model="target" required /></label>
                    @error('target')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                    <button class="button" wire:loading.attr="disabled">+ Criar meta</button>
                </form>
        </section>@endcan
    </div>

</div>
