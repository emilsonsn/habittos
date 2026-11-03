<div>
    <header class="page-heading routine-heading">
        <div><span class="eyebrow">UM DIA DE CADA VEZ</span><h1>Minha rotina<span class="purple">.</span></h1>
            <p class="muted">Pequenas ações, espaço para o que importa. Organize sua semana e acompanhe cada conquista.</p></div>
    </header>
    @if ($message)<div class="notice success" role="status">{{ $message }}</div>@endif
    <div class="month-navigation">
        <div><span class="eyebrow">SEMANA DE REFERÊNCIA</span><h2>Semana {{ $week }} de {{ $year }}</h2>
            <p class="muted">{{ $weekStart->format('d/m/Y') }} a {{ $weekStart->addDays(6)->format('d/m/Y') }}</p></div>
        <div class="month-controls">
            <button type="button" class="button secondary month-step" wire:click="navigateWeek(-1)" aria-label="Semana anterior">‹ Semana</button>
            <button type="button" class="button secondary month-step" wire:click="openCurrentWeek">Semana atual</button>
            <button type="button" class="button secondary month-step" wire:click="navigateWeek(1)" aria-label="Próxima semana">Semana ›</button>
        </div>
        @if ($canSynchronize)
            <button type="button" class="button secondary" wire:click="synchronizePreviousWeek"
                wire:loading.attr="disabled" wire:target="synchronizePreviousWeek"
                data-confirm="As atividades da semana anterior serão copiadas para esta semana, nos mesmos dias, com todos os checks desmarcados. O histórico será preservado."
                data-confirm-title="Sincronizar semana anterior?" data-confirm-label="Sincronizar semana"
                data-confirm-tone="{{ \App\Enums\ConfirmationTone::Warning->value }}"
                data-confirm-subject="{{ $weekStart->subWeek()->format('d/m/Y') }} a {{ $weekStart->subDay()->format('d/m/Y') }}">
                Sincronizar semana anterior
            </button>
        @endif
        @error('synchronization')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        <small>Em uma semana vazia, sincronize para copiar as atividades da semana anterior com todos os checks desmarcados.</small>
    </div>
    <section class="panel routine-workspace" aria-label="Rotina semanal">
        <div class="routine-toolbar">
            <div><h2>Sua semana, em pequenos passos</h2><p class="muted">Organize seus dias ou copie as atividades da semana anterior.</p></div>
            <span class="routine-total">{{ $activities->whereNotNull('completed_at')->count() }} de {{ $activities->count() }} concluídas</span>
        </div>
        @if ($future)
            <p class="routine-context">Esta semana ainda não começou. Você já pode sincronizar as atividades da semana anterior; os checks serão liberados quando ela começar.</p>
        @elseif (!$weekStart->isSameDay(app(\App\Services\RoutineCalendar::class)->currentWeek()))
            <p class="routine-context">Histórico da semana · apenas consulta. As marcações desta semana estão preservadas.</p>
        @endif
        <div class="routine-sheet-scroll" tabindex="0" role="region" aria-label="Grade de segunda a domingo">
            <div class="routine-sheet">
                @foreach ($days as $day)
                    @php
                        $date = $weekStart->addDays($day->value - 1);
                        $items = $activities->filter(fn ($item) => $item->weekday === $day);
                        $done = $items->whereNotNull('completed_at')->count();
                    @endphp
                    <section class="routine-day {{ $date->isToday() ? 'is-today' : '' }}" aria-label="{{ $day->label() }}" wire:key="routine-day-{{ $weekStart->toDateString() }}-{{ $day->value }}">
                        <header class="routine-day-heading">
                            <div><h3>{{ $day->label() }}</h3><time datetime="{{ $date->toDateString() }}">{{ $date->format('d/m') }}</time></div>
                            <span class="routine-day-count">{{ $done }}/{{ $items->count() }}</span>
                            @if ($date->isToday())<span class="routine-today">Hoje</span>@endif
                        </header>
                        <div class="routine-day-body">
                            @forelse ($items as $item)
                                <div class="routine-task {{ $item->completed_at ? 'is-completed' : '' }}" wire:key="routine-task-{{ $item->id }}">
                                    @if ($editable && $editingId === $item->id)
                                        <form wire:submit="saveTask" class="routine-edit-form" x-data x-init="$nextTick(() => $refs.title.focus())" @keydown.escape.prevent.stop="$wire.cancelEditing()">
                                            <label class="sr-only" for="routine-edit-{{ $item->id }}">Texto da atividade de {{ $day->label() }}</label>
                                            <input id="routine-edit-{{ $item->id }}" x-ref="title" type="text" wire:model="editingTitle" maxlength="200" autocomplete="off" required aria-describedby="routine-edit-error-{{ $item->id }}" @error('editingTitle') aria-invalid="true" @enderror>
                                            @error('editingTitle')<p id="routine-edit-error-{{ $item->id }}" class="field-error" role="alert">{{ $message }}</p>@enderror
                                            <div class="routine-edit-actions">
                                                <button type="submit" aria-label="Salvar" title="Salvar" wire:loading.attr="disabled" wire:target="saveTask">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                </button>
                                                <button type="button" aria-label="Cancelar" title="Cancelar" wire:click="cancelEditing" wire:loading.attr="disabled" wire:target="saveTask">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke-linecap="round"/></svg>
                                                </button>
                                            </div>
                                        </form>
                                    @else
                                        <label><input type="checkbox" @checked($item->completed_at) @disabled(!$editable)
                                            aria-label="Concluir {{ $item->title }} em {{ $day->label() }}"
                                            wire:change="setCompleted({{ $item->id }}, $event.target.checked)" wire:loading.attr="disabled" wire:target="setCompleted,removeTask">
                                            <span>{{ $item->title }}</span></label>
                                        @if ($editable)
                                            <div class="routine-task-actions">
                                                <button type="button" class="routine-edit" wire:click="editTask({{ $item->id }})" aria-label="Editar {{ $item->title }}" title="Editar atividade" wire:loading.attr="disabled">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m16 3 5 5L8 21H3v-5L16 3Z" stroke-linejoin="round"/><path d="m14 5 5 5"/></svg>
                                                </button>
                                                <button type="button" class="routine-remove" wire:click="removeTask({{ $item->id }})" data-confirm="A atividade será removida desta semana. As atividades e os checks das semanas anteriores serão preservados." data-confirm-title="Remover atividade da rotina?" data-confirm-label="Remover atividade" data-confirm-subject="{{ $item->title }}"
                                                    aria-label="Remover {{ $item->title }} da rotina" title="Remover da rotina" wire:loading.attr="disabled">×</button>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            @empty
                                <p class="routine-day-empty">{{ $future ? 'Um novo começo em breve.' : 'Um espaço para seus planos.' }}</p>
                            @endforelse
                            @if ($editable)
                                <form wire:submit="addTask({{ $day->value }})" class="routine-add">
                                    <label class="sr-only" for="routine-new-{{ $day->value }}">Nova atividade de {{ $day->label() }}</label>
                                    <input id="routine-new-{{ $day->value }}" type="text" wire:model="titles.{{ $day->value }}" maxlength="200" placeholder="Adicionar atividade…" autocomplete="off" required>
                                    <button type="submit" aria-label="Adicionar atividade de {{ $day->label() }}" wire:loading.attr="disabled" wire:target="addTask">+</button>
                                </form>
                                @error('titles.'.$day->value)<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
        <p class="routine-footnote">De segunda a domingo, no seu ritmo. Os checks são salvos automaticamente em cada semana.</p>
    </section>
</div>
