            <div class="study-steps " @if ($canManage) wire:sort="reorderStep" wire:sort:config="{ handle: '.study-step-handle', draggable: '.study-step' }" @endif>
                @forelse ($roadmap->steps as $step)
                    <section class="panel study-step" wire:key="study-step-{{ $step->id }}" @if ($canManage) wire:sort:item="{{ $step->id }}" @endif>
                        <header class="study-step-heading"><div class="study-title-row">
                            @if ($canManage)<button type="button" class="study-drag study-step-handle" wire:sort:handle aria-label="Reordenar etapa {{ $step->title }}" title="Arraste para ordenar; use as setas para cima e para baixo"
                                @if (!$loop->first) @keydown.up.prevent="$wire.reorderStep({{ $step->id }}, {{ $loop->index - 1 }})" @endif
                                @if (!$loop->last) @keydown.down.prevent="$wire.reorderStep({{ $step->id }}, {{ $loop->index + 1 }})" @endif>⠿</button>@endif
                            <h3>{{ $step->title }}</h3><small>{{ $step->topics->where('status', \App\Enums\StudyStatus::Completed)->count() }}/{{ $step->topics->count() }}</small>
                        </div>
                        @if ($canManage)<div class="actions"><button type="button" class="text-button" wire:click="openForm('step', {{ $step->id }}, {{ $roadmap->id }})">Editar</button>
                            <button type="button" class="text-button" wire:click="openForm('topic', null, {{ $step->id }})">+ Tópico</button>
                            <button type="button" class="text-button danger" wire:click="deleteRecord('step', {{ $step->id }})" data-confirm="A etapa e seus tópicos serão excluídos. Os materiais e o histórico de sessões serão preservados." data-confirm-title="Excluir etapa?" data-confirm-subject="{{ $step->title }}" data-confirm-label="Excluir etapa" wire:loading.attr="disabled">Excluir</button></div>@endif
                        </header>
                        <div class="study-topic-list" @if ($canManage) wire:sort="reorderTopic" wire:sort:group-id="{{ $step->id }}" wire:sort:group="study-roadmap-{{ $roadmap->id }}" wire:sort:config="{ handle: '.study-topic-handle', draggable: '.study-topic' }" @endif>
                            @forelse ($step->topics as $topic)
                                <article class="study-topic {{ $topic->status === \App\Enums\StudyStatus::Completed ? 'is-completed' : '' }}" wire:key="study-topic-{{ $topic->id }}" @if ($canManage) wire:sort:item="{{ $topic->id }}" @endif>
                                    <div class="study-title-row">
                                        @if ($canManage)<button type="button" class="study-drag study-topic-handle" wire:sort:handle aria-label="Reordenar tópico {{ $topic->title }}" title="Arraste para ordenar ou mover entre etapas"
                                            @if (!$loop->first) @keydown.up.prevent="$wire.reorderTopic({{ $topic->id }}, {{ $loop->index - 1 }})" @endif
                                            @if (!$loop->last) @keydown.down.prevent="$wire.reorderTopic({{ $topic->id }}, {{ $loop->index + 1 }})" @endif>⠿</button>@endif
                                        <label class="study-topic-title"><input type="checkbox" @checked($topic->status === \App\Enums\StudyStatus::Completed) @disabled(!$canManage) wire:change="setTopicStatus({{ $topic->id }}, $event.target.checked ? 'completed' : 'in_progress')" aria-label="Concluir {{ $topic->title }}" wire:loading.attr="disabled"><span>{{ $topic->title }}</span></label>
                                    </div>
                                    <span class="tag">{{ $topic->status->label() }}</span>
                                    @if ($topic->checkpoint)<p class="study-checkpoint"><small>De onde continuar</small>{{ $topic->checkpoint }}</p>@endif
                                    @if ($topic->description)<details class="study-topic-notes"><summary>Anotações e detalhes</summary><div class="rich-text-content rich-text-readonly">{!! $topic->description !!}</div></details>@endif
                                    @if ($canManage)<div class="study-topic-actions"><button type="button" class="button secondary" wire:click="openForm('session', null, {{ $topic->id }})">Estudei hoje</button>
                                        <button type="button" class="text-button" wire:click="openForm('topic', {{ $topic->id }})">Editar</button>
                                        <button type="button" class="text-button" wire:click="openForm('material', null, {{ $topic->id }})">+ Material</button>
                                        <button type="button" class="text-button danger" wire:click="deleteRecord('topic', {{ $topic->id }})" data-confirm="O tópico será excluído. Os materiais e as sessões registradas serão preservados." data-confirm-title="Excluir tópico?" data-confirm-subject="{{ $topic->title }}" data-confirm-label="Excluir tópico" wire:loading.attr="disabled">Excluir</button></div>@endif
                                </article>
                            @empty<p class="muted study-empty">Adicione o primeiro tópico desta etapa.</p>@endforelse
                        </div>
                    </section>
                @empty<div class="panel study-empty">Seu caminho começa com uma etapa. Que tal separar o básico, a prática e o aprofundamento?</div>@endforelse
            </div>
