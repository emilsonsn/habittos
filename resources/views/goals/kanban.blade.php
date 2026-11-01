<div class="goals-kanban" aria-label="Kanban de metas por status" x-data="HabittosGoalKanban.create($wire)"
    @pointermove.window="move($event)" @pointerup.window="end($event)" @pointercancel.window="cancel()" @blur.window="cancel()">
    @can('goals.manage', $workspace)
        <p class="sr-only" id="goal-kanban-help">Arraste uma meta para outra coluna para mudar seu status. Use Enter para editar ou Alt com as setas esquerda e direita para mover entre as colunas.</p>
    @endcan
    <div class="goal-drag-preview" x-cloak x-show="dragged !== null" :style="{ left: (x + 14) + 'px', top: (y + 14) + 'px' }" x-text="title" aria-hidden="true"></div>
    @foreach (\App\Models\Goal::STATUSES as $status => $label)
        @php
            $cards = $goals->where('status', $status);
            $statuses = array_keys(\App\Models\Goal::STATUSES);
            $statusIndex = array_search($status, $statuses, true);
        @endphp
        <section class="goal-kanban-column" aria-label="{{ $label }}" wire:key="goal-column-{{ $status }}"
            :class="{ 'is-drop-target': over === '{{ $status }}' }"
            data-goal-status="{{ $status }}">
            <h2><span class="goal-status {{ $status }}">{{ $label }}</span><span class="count">{{ $cards->count() }}</span></h2>
            <div class="goal-kanban-cards">
                @forelse ($cards as $goal)
                    <article class="goal-card" wire:key="goal-card-{{ $goal->id }}"
                        :class="{ 'is-dragging': dragged === {{ $goal->id }} }"
                        tabindex="0" role="button" aria-label="Abrir meta: {{ $goal->title }}"
                        @click="open({{ $goal->id }})"
                        @keydown.enter.prevent="$wire.openDetails({{ $goal->id }})"
                        @keydown.space.prevent="$wire.openDetails({{ $goal->id }})"
                        @can('goals.manage', $workspace)
                            data-goal-draggable aria-describedby="goal-kanban-help"
                            @pointerdown="start($event, {{ $goal->id }}, @js($goal->title))"
                            @if ($statusIndex > 0)
                                @keydown.alt.arrow-left.prevent="$wire.changeStatus({{ $goal->id }}, '{{ $statuses[$statusIndex - 1] }}')"
                            @endif
                            @if ($statusIndex < count($statuses) - 1)
                                @keydown.alt.arrow-right.prevent="$wire.changeStatus({{ $goal->id }}, '{{ $statuses[$statusIndex + 1] }}')"
                            @endif
                        @endcan>
                        <div class="goal-card-tags"><span class="goal-priority {{ $goal->priority }}">{{ \App\Models\Goal::PRIORITIES[$goal->priority] }}</span>
                            @if ($goal->category)<span class="tag">{{ $goal->category }}</span>@endif
                        </div>
                        <h3>{{ $goal->title }}</h3>
                        @include('goals.progress')
                    </article>
                @empty
                    <p class="muted goal-column-empty">Nenhuma meta neste status.</p>
                @endforelse
            </div>
        </section>
    @endforeach
</div>
