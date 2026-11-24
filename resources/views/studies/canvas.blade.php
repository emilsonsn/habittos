<section class="study-canvas" :class="{ 'is-expanded': expanded, 'is-connecting': connecting }" style="--study-color: {{ $roadmap->color }}"
    wire:key="study-canvas-{{ $roadmap->id }}" x-data="HabittosStudyCanvas.create($wire, @js($canvasState), @js($canManage))"
    aria-label="Quadro visual de {{ $roadmap->title }}"
    @pointermove.window="move($event)" @pointerup.window="end($event)" @pointercancel.window="cancelGesture()" @blur.window="cancelGesture()"
    @keydown.escape.prevent.stop="escape()">
    <header class="study-canvas-toolbar">
        <div class="study-canvas-identity"><span class="study-canvas-symbol" aria-hidden="true"><x-icon name="studies" /></span><div><strong>Seu mapa de aprendizado</strong><small>{{ $roadmap->steps->count() }} etapas · {{ $roadmap->topics_count }} tópicos</small></div></div>
        <div class="study-canvas-tools">
            @if ($canManage)
                <button type="button" class="study-canvas-tool" @click="toggleConnect()" :aria-pressed="connecting" :disabled="busy" title="Escolha duas etapas para criar uma ligação"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="5" cy="5" r="3"/><circle cx="19" cy="19" r="3"/><path d="M8 5h5a6 6 0 0 1 6 6v5"/></svg><span>Conectar</span></button>
                <button type="button" class="study-canvas-tool" @click="organize()" :disabled="busy || !nodes.length" title="Organizar as posições seguindo as conexões"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="8" y="2" width="8" height="5" rx="1"/><rect x="2" y="17" width="7" height="5" rx="1"/><rect x="15" y="17" width="7" height="5" rx="1"/><path d="M12 7v5M5 17v-5h14v5"/></svg><span>Organizar</span></button>
                <button type="button" class="study-canvas-tool is-primary" wire:click="openForm('step', null, {{ $roadmap->id }})" wire:loading.attr="disabled">+ Nova etapa</button>
            @endif
            <button type="button" class="study-canvas-tool" @click="toggleExpanded()" :aria-expanded="expanded" :aria-label="expanded ? 'Recolher quadro' : 'Expandir quadro'" :title="expanded ? 'Recolher quadro' : 'Expandir quadro'"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5"/></svg></button>
        </div>
    </header>
    <div class="study-canvas-viewport" x-ref="viewport" :style="gridStyle()" :class="{ 'is-panning': gesture?.type === 'pan' }"
        @pointerdown="startPan($event)" @wheel.prevent="wheel($event)"
        @keydown.left.self.prevent="panX += 48" @keydown.right.self.prevent="panX -= 48" @keydown.up.self.prevent="panY += 48" @keydown.down.self.prevent="panY -= 48" tabindex="0" role="region" aria-label="Área navegável do mapa. Arraste o fundo para navegar e use os controles para ampliar.">
        <div class="study-canvas-world" :style="worldStyle()">
            <svg class="study-canvas-links" width="1" height="1" aria-label="Conexões entre etapas">
                <defs><marker id="study-arrow-{{ $roadmap->id }}" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M 1 1 L 9 5 L 1 9" fill="none" stroke="context-stroke" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></marker></defs>
                @foreach ($canvasState['edges'] as $edge)
                    <g wire:key="study-canvas-edge-{{ $edge['id'] }}" :class="{ 'is-selected': selectedEdge === {{ $edge['id'] }}, 'is-complete': edgeComplete({ source: {{ $edge['source'] }} }) }">
                        <path class="study-canvas-edge" :d="edgePath({ source: {{ $edge['source'] }}, target: {{ $edge['target'] }} })" marker-end="url(#study-arrow-{{ $roadmap->id }})" />
                        <path class="study-canvas-edge-hit" :d="edgePath({ source: {{ $edge['source'] }}, target: {{ $edge['target'] }} })" data-canvas-edge tabindex="0" role="button" :aria-label="'Selecionar ligação de ' + (node({{ $edge['source'] }})?.title ?? '') + ' para ' + (node({{ $edge['target'] }})?.title ?? '')"
                            @click.stop="selectedEdge = {{ $edge['id'] }}; selectedTopic = null; cancelConnection()" @keydown.enter.prevent="selectedEdge = {{ $edge['id'] }}; selectedTopic = null; cancelConnection()" />
                    </g>
                @endforeach
            </svg>
            @foreach ($roadmap->steps as $step)
                @php($completeCount = $step->topics->where('status', \App\Enums\StudyStatus::Completed)->count())
                @php($stepComplete = $step->topics->isNotEmpty() && $completeCount === $step->topics->count())
                <article class="study-map-node {{ $stepComplete ? 'is-complete' : '' }}" data-study-node="{{ $step->id }}" wire:key="study-map-node-{{ $step->id }}"
                    :style="style({{ $step->id }})" :class="{ 'is-dragging': gesture?.id === {{ $step->id }}, 'is-source': sourceId === {{ $step->id }}, 'is-target': connecting && sourceId !== null && sourceId !== {{ $step->id }} }">
                    @if ($canManage)<button type="button" class="study-map-port port-top" @click.stop="linkNode({{ $step->id }})" :disabled="busy" aria-label="Conectar etapa {{ $step->title }}" title="Clique aqui e depois em outra etapa para conectar"></button>@endif
                    <div class="study-map-node-heading">
                        <div class="study-map-node-title" @if ($canManage) @pointerdown.stop="startNode($event, {{ $step->id }})" @endif>
                            @if ($canManage)<button type="button" class="study-map-grip" aria-label="Mover etapa {{ $step->title }}" title="Arraste para posicionar. Use as setas para mover pelo teclado."
                                @keydown.left.prevent.stop="nudge({{ $step->id }}, $event.shiftKey ? -64 : -16, 0)" @keydown.right.prevent.stop="nudge({{ $step->id }}, $event.shiftKey ? 64 : 16, 0)"
                                @keydown.up.prevent.stop="nudge({{ $step->id }}, 0, $event.shiftKey ? -64 : -16)" @keydown.down.prevent.stop="nudge({{ $step->id }}, 0, $event.shiftKey ? 64 : 16)" :disabled="busy"><span aria-hidden="true">⠿</span></button>@endif
                            <div><span class="study-map-step-label">ETAPA {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><h3>{{ $step->title }}</h3></div>
                        </div>
                        @if ($canManage)<details class="study-map-menu"><summary aria-label="Ações da etapa {{ $step->title }}" title="Ações da etapa">⋯</summary><div>
                            <button type="button" wire:click="openForm('step', {{ $step->id }}, {{ $roadmap->id }})">Editar etapa</button>
                            <button type="button" wire:click="openForm('topic', null, {{ $step->id }})">Adicionar tópico</button>
                            <button type="button" class="danger" wire:click="deleteRecord('step', {{ $step->id }})" data-confirm="A etapa e seus tópicos serão excluídos. Os materiais e o histórico de sessões serão preservados." data-confirm-title="Excluir etapa?" data-confirm-subject="{{ $step->title }}" data-confirm-label="Excluir etapa" wire:loading.attr="disabled">Excluir etapa</button>
                        </div></details>@endif
                    </div>
                    <div class="study-map-progress"><span style="width: {{ $step->topics->count() ? $completeCount / $step->topics->count() * 100 : 0 }}%"></span></div>
                    <div class="study-map-topics">
                        @forelse ($step->topics as $topic)
                            <div class="study-map-topic {{ $topic->status === \App\Enums\StudyStatus::Completed ? 'is-complete' : '' }}" wire:key="study-map-topic-{{ $topic->id }}" :class="{ 'is-selected': selectedTopic === {{ $topic->id }} }">
                                <input type="checkbox" @checked($topic->status === \App\Enums\StudyStatus::Completed) @disabled(!$canManage) wire:change="setTopicStatus({{ $topic->id }}, $event.target.checked ? 'completed' : 'in_progress')" aria-label="Concluir {{ $topic->title }}" wire:loading.attr="disabled">
                                <button type="button" @click.stop="selectTopic({{ $topic->id }})" :aria-pressed="selectedTopic === {{ $topic->id }}"><span>{{ $topic->title }}</span><span class="study-map-status-dot status-{{ $topic->status->value }}" title="{{ $topic->status->label() }}"><span class="sr-only">{{ $topic->status->label() }}</span></span></button>
                            </div>
                        @empty<p class="study-map-node-empty">Separe este passo em pequenos tópicos.</p>@endforelse
                    </div>
                    <footer class="study-map-node-footer"><small>{{ $completeCount }}/{{ $step->topics->count() }} concluídos</small>
                        @if ($canManage)<button type="button" wire:click="openForm('topic', null, {{ $step->id }})" wire:loading.attr="disabled">+ Tópico</button>@endif</footer>
                    @if ($canManage)<button type="button" class="study-map-port port-bottom" @click.stop="linkNode({{ $step->id }})" :disabled="busy" aria-label="Criar ligação a partir de {{ $step->title }}" title="Conectar com outra etapa"></button>@endif
                </article>
            @endforeach
        </div>
        @if ($roadmap->steps->isEmpty())<div class="study-canvas-empty"><div class="study-canvas-empty-icon" aria-hidden="true"><x-icon name="studies" width="30" height="30" /></div><span class="eyebrow">UM ESPAÇO PARA SUAS IDEIAS</span><h3>Todo caminho começa com um primeiro passo.</h3><p>Crie uma etapa, adicione os tópicos e desenhe as conexões do seu aprendizado.</p>
            @if ($canManage)<button type="button" class="button" wire:click="openForm('step', null, {{ $roadmap->id }})">+ Criar primeira etapa</button>@endif</div>@endif
        <div class="study-canvas-instruction" x-cloak x-show="connecting" role="status"><span x-text="sourceId === null ? 'Escolha a etapa de origem.' : 'Agora escolha a etapa de destino.'"></span><button type="button" @click="cancelConnection()">Cancelar</button></div>
        @if ($canManage)<div class="study-canvas-connection-actions" x-cloak x-show="selectedEdge !== null && !connecting"><span>Ligação selecionada</span><button type="button" class="danger" @click="disconnect()" :disabled="busy">Remover ligação</button><button type="button" @click="selectedEdge = null" aria-label="Desmarcar ligação">×</button></div>@endif
        @foreach ($roadmap->steps as $step)
            @foreach ($step->topics as $topic)
                <aside class="study-map-inspector" data-study-inspector wire:key="study-inspector-{{ $topic->id }}" x-cloak x-show="selectedTopic === {{ $topic->id }}" @wheel.stop @pointerdown.stop role="region" aria-label="Detalhes de {{ $topic->title }}">
                    <header><span class="eyebrow">{{ $step->title }}</span><button type="button" @click="selectedTopic = null" aria-label="Fechar detalhes do tópico">×</button></header>
                    <h3>{{ $topic->title }}</h3><span class="tag">{{ $topic->status->label() }}</span>
                    @if ($topic->checkpoint)<div class="study-checkpoint"><small>DE ONDE CONTINUAR</small>{{ $topic->checkpoint }}</div>@endif
                    @if ($topic->description)<div class="rich-text-content rich-text-readonly">{!! $topic->description !!}</div>@else<p class="muted study-inspector-empty">Guarde aqui os detalhes e as anotações deste tópico.</p>@endif
                    <div class="study-inspector-materials"><a wire:navigate href="{{ route('studies.library', [$workspace, 'roadmap' => $roadmap->id]) }}">Ver materiais da trilha →</a></div>
                    @if ($canManage)<div class="study-inspector-actions">
                        <button type="button" class="button" wire:click="openForm('session', null, {{ $topic->id }})">Estudei hoje</button>
                        <button type="button" class="button secondary" wire:click="openForm('topic', {{ $topic->id }})">Editar tópico</button>
                        <button type="button" class="text-button" wire:click="openForm('material', null, {{ $topic->id }})">+ Adicionar material</button>
                        <div class="study-inspector-order"><button type="button" class="text-button" wire:click="reorderTopic({{ $topic->id }}, {{ max(0, $loop->index - 1) }})" @disabled($loop->first) wire:loading.attr="disabled">↑ Subir tópico</button><button type="button" class="text-button" wire:click="reorderTopic({{ $topic->id }}, {{ min($step->topics->count() - 1, $loop->index + 1) }})" @disabled($loop->last) wire:loading.attr="disabled">↓ Descer tópico</button></div>
                        <button type="button" class="text-button danger" wire:click="deleteRecord('topic', {{ $topic->id }})" data-confirm="O tópico será excluído. Os materiais e as sessões registradas serão preservados." data-confirm-title="Excluir tópico?" data-confirm-subject="{{ $topic->title }}" data-confirm-label="Excluir tópico" wire:loading.attr="disabled">Excluir tópico</button>
                    </div>@endif
                </aside>
            @endforeach
        @endforeach
        <div class="study-canvas-zoom"><button type="button" @click="zoom(1 / 1.2)" aria-label="Diminuir zoom">−</button><span x-text="Math.round(scale * 100) + '%'" aria-label="Nível de zoom"></span><button type="button" @click="zoom(1.2)" aria-label="Aumentar zoom">+</button><span class="study-canvas-control-divider"></span><button type="button" @click="fit()" title="Enquadrar toda a trilha" aria-label="Enquadrar toda a trilha"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2"/><path d="M3 8V3h5m8 0h5v5M3 16v5h5m8 0h5v-5"/></svg></button></div>
        <div class="study-canvas-save" role="status" :class="{ 'has-error': error }" x-cloak x-show="notice"><span class="study-canvas-save-dot" :class="{ 'is-busy': busy }"></span><span x-text="notice"></span></div>
    </div>
    <footer class="study-canvas-footer"><span>Arraste o fundo para navegar · Ctrl + rolagem para zoom @if ($canManage) · Arraste as etapas para posicionar @endif</span><span class="study-canvas-legend"><i></i>Conexões do seu aprendizado</span></footer>
</section>
