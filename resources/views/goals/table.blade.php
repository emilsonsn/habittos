<div class="table-scroll" tabindex="0" role="region" aria-label="Tabela de metas">
    <table class="goals-table">
        <thead><tr>
            @foreach (['Nome da meta', 'Status', 'Prioridade', 'Progresso', 'Prazo'] as $column)
                <th scope="col">{{ $column }}</th>
            @endforeach
        </tr></thead>
        <tbody>
            @foreach ($goals as $goal)
                <tr wire:key="goal-row-{{ $goal->id }}">
                    <td class="goal-title-cell">
                        <button type="button" class="text-button goal-title-button" wire:click="openDetails({{ $goal->id }})">{{ $goal->title }}</button>
                        @if ($goal->parent)<small>↳ {{ $goal->parent->title }}</small>@endif
                    </td>
                    <td><span class="goal-status {{ $goal->status }}">{{ \App\Enums\GoalStatus::options()[$goal->status] }}</span></td>
                    <td><span class="goal-priority {{ $goal->priority }}">{{ \App\Enums\GoalPriority::options()[$goal->priority] }}</span></td>
                    <td>@include('goals.progress')</td>
                    <td @class(['negative' => $goal->due_date?->isBefore(today()) && $goal->status !== \App\Enums\GoalStatus::Completed->value])>{{ $goal->due_date?->format('d/m/Y') ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@if ($goals->hasPages())
    <nav class="goals-pagination" aria-label="Paginação das metas">
        <button type="button" class="button secondary" wire:click="previousPage" @disabled($goals->onFirstPage())>Anterior</button>
        <span>Página {{ $goals->currentPage() }} de {{ $goals->lastPage() }}</span>
        <button type="button" class="button secondary" wire:click="nextPage" @disabled(!$goals->hasMorePages())>Próxima</button>
    </nav>
@endif
