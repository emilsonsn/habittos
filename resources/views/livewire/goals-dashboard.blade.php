<div class="goals-dashboard">
    @php
        $goalUrl = fn ($goal) => route('goals.'.$goal->period, ['workspace' => $workspace, 'year' => $year, 'month' => $goal->month, 'week' => $goal->week, 'search' => $goal->title]);
    @endphp
    <div class="page-heading">
        <div><span class="eyebrow">SEUS PLANOS EM PERSPECTIVA</span><h1>Dashboard de metas<span class="heading-dot">.</span></h1>
            <p>Acompanhe suas conquistas e escolha onde concentrar o próximo passo.</p></div>
        <label>Ano<select wire:model.live="year">
            @for ($option = 2000; $option <= 2100; $option++)<option value="{{ $option }}">{{ $option }}</option>@endfor
        </select></label>
    </div>
    <div class="metrics goal-dashboard-metrics">
        <article class="metric"><h2 class="metric-label">Metas no ano</h2><strong>{{ $total }}</strong><small>Anuais, mensais e semanais</small></article>
        <article class="metric"><h2 class="metric-label">Concluídas</h2><strong>{{ $completed }}</strong><small>{{ number_format($completed / max(1, $total) * 100, 0, ',', '.') }}% das metas do ano</small></article>
        <article class="metric"><h2 class="metric-label">Em andamento</h2><strong>{{ $inProgress }}</strong><small>Planos em movimento</small></article>
        <article class="metric"><h2 class="metric-label">Progresso médio</h2><strong>{{ number_format($average, 1, ',', '.') }}%</strong><small>Média do campo Feito (%)</small></article>
    </div>
    <div class="goal-analytics-grid">
        <section class="panel goal-analytics-panel">
            <div class="section-heading"><div><span class="eyebrow">COMO ESTÃO OS PLANOS</span><h2>Por status</h2></div><span class="tag">{{ $year }}</span></div>
            <x-goal-status-chart :statuses="$byStatus" :total="$total" />
            @if (!$total)<p class="muted goal-chart-empty">Nenhuma meta neste ano.</p>@endif
        </section>
        <section class="panel goal-analytics-panel">
            <div class="section-heading"><div><span class="eyebrow">CADA ÁREA DA SUA VIDA</span><h2>Progresso por categoria</h2></div><span class="tag">{{ $byCategory->count() }} categorias</span></div>
            <div class="goal-category-chart">
                @forelse ($byCategory as $category => $summary)
                    <div class="goal-category-bar">
                        <div class="goal-bar-heading"><strong>{{ $category }}</strong><span>{{ number_format($summary['average'], 1, ',', '.') }}%</span></div>
                        <progress value="{{ $summary['average'] }}" max="100" aria-label="Progresso médio em {{ $category }}">{{ $summary['average'] }}%</progress>
                        <div class="goal-bar-caption"><span>{{ $summary['total'] }} {{ $summary['total'] === 1 ? 'meta' : 'metas' }}</span><span>{{ $summary['completed'] }} {{ $summary['completed'] === 1 ? 'concluída' : 'concluídas' }}</span></div>
                    </div>
                @empty
                    <div class="goal-chart-empty"><span aria-hidden="true">◎</span><p>As categorias aparecem quando você registra suas metas.</p></div>
                @endforelse
            </div>
            <p class="muted goal-chart-footnote">Média dos percentuais informados em cada categoria.</p>
        </section>
        <section class="panel goal-analytics-panel">
            <div class="section-heading"><div><span class="eyebrow">ONDE COLOCAR ENERGIA</span><h2>Por prioridade</h2></div></div>
            <div class="goal-priority-chart">
                @foreach ($byPriority as $priority => $summary)
                    <div>
                        <div class="goal-bar-heading"><span class="goal-priority {{ $priority }}">{{ $summary['label'] }}</span><span>{{ $summary['total'] }} {{ $summary['total'] === 1 ? 'meta' : 'metas' }}</span></div>
                        <div class="goal-stacked-bar" role="img" aria-label="Prioridade {{ $summary['label'] }}: {{ $summary['statuses']->map(fn ($item) => $item['count'].' '.$item['name'])->implode(', ') }}">
                            @foreach ($summary['statuses'] as $status)
                                @if ($status['count'])<span style="width: {{ $status['count'] / max(1, $summary['total']) * 100 }}%; background: {{ $status['color'] }}" title="{{ $status['name'] }}: {{ $status['count'] }}"></span>@endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="goal-priority-key" aria-hidden="true">
                @foreach ($byStatus as $status)<span><i class="goal-chart-dot" style="background: {{ $status['color'] }}"></i>{{ $status['name'] }}</span>@endforeach
            </div>
        </section>
        <section class="panel goal-analytics-panel">
            <div class="section-heading"><div><span class="eyebrow">DO ANO À SEMANA</span><h2>Seus períodos</h2></div><span class="tag">{{ $year }}</span></div>
            <div class="goal-period-charts">
                @foreach ($byPeriod as $period => $summary)
                    <a wire:navigate class="goal-period-chart" href="{{ route('goals.'.$period, [$workspace, 'year' => $year]) }}">
                        <span class="goal-period-chart-title">{{ \App\Enums\GoalSection::from($period)->label() }} <span aria-hidden="true">↗</span></span>
                        <strong>{{ $summary['total'] }} <small>{{ $summary['total'] === 1 ? 'meta' : 'metas' }}</small></strong>
                        <progress value="{{ $summary['average'] }}" max="100" aria-label="Progresso médio das metas {{ \App\Enums\GoalPeriod::from($period)->plural() }}">{{ $summary['average'] }}%</progress>
                        <span class="goal-period-chart-average">{{ number_format($summary['average'], 1, ',', '.') }}% de progresso médio</span>
                        <span class="goal-period-chart-caption">{{ $summary['completed'] }} concluídas · {{ $summary['in_progress'] }} em andamento</span>
                    </a>
                @endforeach
            </div>
            <p class="muted goal-chart-footnote">Concluir uma meta define Feito (%) como 100. Os vínculos não somam percentuais.</p>
        </section>
    </div>
    <div class="goal-deadlines-grid">
        <section class="panel goal-deadline-panel">
            <div class="section-heading"><div><span class="eyebrow">OS PRÓXIMOS PASSOS</span><h2>Próximos prazos</h2></div><span class="tag">{{ $year }}</span></div>
            <div class="goal-deadline-list">
                @forelse ($upcoming as $goal)
                    <a wire:navigate class="goal-deadline-row" href="{{ $goalUrl($goal) }}">
                        <span><strong>{{ $goal->title }}</strong><small>{{ $goal->category ?? 'Sem categoria' }} · {{ $goal->periodLabel() }}</small></span><time datetime="{{ $goal->due_date->toDateString() }}">{{ $goal->due_date->format('d/m/Y') }}</time>
                    </a>
                @empty<div class="goal-chart-empty"><span aria-hidden="true">✓</span><p>Nenhum prazo futuro para as metas em aberto deste ano.</p></div>@endforelse
            </div>
        </section>
        <section class="panel goal-deadline-panel">
            <div class="section-heading"><div><span class="eyebrow">PEDEM SUA ATENÇÃO</span><h2>Prazos vencidos</h2></div><span class="count">{{ $overdue->count() }}</span></div>
            <div class="goal-deadline-list">
                @forelse ($overdue->take(6) as $goal)
                    <a wire:navigate class="goal-deadline-row" href="{{ $goalUrl($goal) }}">
                        <span><strong>{{ $goal->title }}</strong><small>{{ \App\Enums\GoalStatus::options()[$goal->status] }} · {{ $goal->periodLabel() }}</small></span><time datetime="{{ $goal->due_date->toDateString() }}" class="negative">{{ $goal->due_date->format('d/m/Y') }}</time>
                    </a>
                @empty<div class="goal-chart-empty"><span aria-hidden="true">✓</span><p>Nenhuma meta em aberto com prazo vencido.</p></div>@endforelse
            </div>
        </section>
    </div>
</div>
