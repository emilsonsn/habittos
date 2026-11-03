@foreach (['status', 'priority', 'category', 'progress_percent'] as $field)
    @error($field)<p class="field-error" role="alert">{{ $message }}</p>@enderror
@endforeach
<div class="table-scroll" tabindex="0" role="region" aria-label="Tabela de metas">
    <table class="goals-table">
        <thead><tr>
            @foreach (['Nome da meta', 'Status', 'Prioridade', 'Categoria', 'Progresso'] as $column)
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
                    <td>
                        @can('goals.manage', $workspace)
                            <span class="goal-inline-field goal-status {{ $goal->status }}">
                                <select class="goal-inline-select goal-inline-status" aria-label="Status de {{ $goal->title }}"
                                    wire:change="changeStatus({{ $goal->id }}, $event.target.value)"
                                    wire:loading.attr="disabled" wire:target="changeStatus,changePriority,changeCategory,changeProgress">
                                    @foreach (\App\Enums\GoalStatus::options() as $value => $label)
                                        <option value="{{ $value }}" @selected($goal->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </span>
                        @else
                            <span class="goal-status {{ $goal->status }}">{{ \App\Enums\GoalStatus::from($goal->status)->label() }}</span>
                        @endcan
                    </td>
                    <td>
                        @can('goals.manage', $workspace)
                            <span class="goal-inline-field goal-priority {{ $goal->priority }}">
                                <select class="goal-inline-select goal-inline-priority" aria-label="Prioridade de {{ $goal->title }}"
                                    wire:change="changePriority({{ $goal->id }}, $event.target.value)"
                                    wire:loading.attr="disabled" wire:target="changeStatus,changePriority,changeCategory,changeProgress">
                                    @foreach (\App\Enums\GoalPriority::options() as $value => $label)
                                        <option value="{{ $value }}" @selected($goal->priority === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </span>
                        @else
                            <span class="goal-priority {{ $goal->priority }}">{{ \App\Enums\GoalPriority::from($goal->priority)->label() }}</span>
                        @endcan
                    </td>
                    <td>
                        @can('goals.manage', $workspace)
                            <span class="goal-inline-field">
                                <select class="goal-inline-select goal-inline-category" aria-label="Categoria de {{ $goal->title }}"
                                    wire:change="changeCategory({{ $goal->id }}, $event.target.value)"
                                    wire:loading.attr="disabled" wire:target="changeStatus,changePriority,changeCategory,changeProgress">
                                    <option value="" @selected($goal->category === null)>Sem categoria</option>
                                    @foreach ($categoryOptions as $category)
                                        <option value="{{ $category }}" @selected($goal->category === $category)>{{ $category }}</option>
                                    @endforeach
                                </select>
                            </span>
                        @else
                            {{ $goal->category ?? 'Sem categoria' }}
                        @endcan
                    </td>
                    <td>
                        @can('goals.manage', $workspace)
                            <div class="goal-completion goal-slider" wire:key="goal-progress-{{ $goal->id }}-{{ $goal->progress_percent }}-{{ $goal->status }}"
                                x-data="{ progress: {{ (float) $goal->progress_percent }} }">
                                <output id="goal-progress-output-{{ $goal->id }}" for="goal-progress-{{ $goal->id }}"
                                    x-text="new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(progress) + '%'">{{ number_format((float) $goal->progress_percent, 2, ',', '.') }}%</output>
                                <input type="range" class="goal-progress-slider" id="goal-progress-{{ $goal->id }}"
                                    min="0" max="100" step="1" value="{{ $goal->progress_percent }}"
                                    aria-label="Progresso de {{ $goal->title }}" aria-describedby="goal-progress-output-{{ $goal->id }}"
                                    :style="{ '--goal-progress': progress + '%' }"
                                    @input="progress = Number($event.target.value)"
                                    wire:change="changeProgress({{ $goal->id }}, $event.target.value)"
                                    wire:loading.attr="disabled" wire:target="changeStatus,changePriority,changeCategory,changeProgress">
                            </div>
                        @else
                            @include('goals.progress')
                        @endcan
                    </td>
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
