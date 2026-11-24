<div @class(['studies-page', 'has-open-roadmap' => $section === 'roadmaps' && $roadmap])>
    @if ($section !== 'roadmaps' || !$roadmap)
    <header class="page-heading">
        <div><span class="eyebrow">UM POUCO MAIS A CADA DIA</span><h1>Estudos<span class="heading-dot">.</span></h1>
            <p>Organize o que quer aprender. Dê o próximo passo no seu ritmo.</p></div>
        @if ($canManage)
            <div class="actions">
                @if ($section === 'library')<button type="button" class="button" wire:click="openForm('material')">+ Novo material</button>
                @elseif ($section === 'history')<button type="button" class="button" wire:click="openForm('session')" @disabled($topicOptions->isEmpty())>Estudei hoje</button>
                @else<button type="button" class="button" wire:click="openForm('roadmap')">+ Novo roadmap</button>@endif
            </div>
        @endif
    </header>
    @endif
    @if ($message)<div class="notice success" role="status">{{ $message }}</div>@endif

    @if ($section === 'dashboard')
        <div class="study-metrics">
            <div class="panel"><small>Roadmaps em andamento</small><strong>{{ $stats['active'] }}</strong><span>{{ $stats['roadmaps'] }} no seu ambiente</span></div>
            <div class="panel"><small>Sessões nesta semana</small><strong>{{ $stats['sessions'] }}</strong><span>Um passo de cada vez</span></div>
            <div class="panel"><small>Tempo nesta semana</small><strong>{{ intdiv($stats['minutes'], 60) }}h {{ $stats['minutes'] % 60 }}min</strong><span>Tempo informado nos registros</span></div>
            <div class="panel"><small>Materiais guardados</small><strong>{{ $stats['materials'] }}</strong><span>Seus arquivos e referências</span></div>
        </div>
        <section class="panel study-continue">
            <div><span class="eyebrow">SEU PRÓXIMO PASSO</span><h2>Continuar de onde parei</h2>
                @if ($nextTopic)
                    <h3>{{ $nextTopic->title }}</h3><p class="muted">{{ $nextTopic->roadmap->title }}</p>
                    @if ($nextTopic->checkpoint)<p class="study-checkpoint">{{ $nextTopic->checkpoint }}</p>@endif
                @else<p class="muted">Seu próximo aprendizado começa com um roadmap. Adicione etapas e tópicos para organizar o caminho.</p>@endif
            </div>
            @if ($nextTopic)
                <div class="actions"><a wire:navigate class="button secondary" href="{{ route('studies.roadmaps', [$workspace, 'roadmap' => $nextTopic->study_roadmap_id]) }}">Abrir roadmap</a>
                    @if ($canManage)<button type="button" class="button" wire:click="openForm('session', null, {{ $nextTopic->id }})">Estudei hoje</button>@endif</div>
            @elseif ($canManage)<button type="button" class="button" wire:click="openForm('roadmap')">Criar meu primeiro roadmap</button>@endif
        </section>
        <div class="section-heading"><h2>Seus caminhos de aprendizado</h2><a wire:navigate href="{{ route('studies.roadmaps', $workspace) }}">Ver roadmaps →</a></div>
        @include('studies.roadmap-cards')
    @elseif ($section === 'roadmaps')
        @if ($roadmap)
            <a wire:navigate class="study-back" href="{{ route('studies.roadmaps', $workspace) }}">← Todos os roadmaps</a>
            <section class="panel study-roadmap-heading" style="--study-color: {{ $roadmap->color }}">
                <div><span class="tag">{{ $roadmap->status->label() }}</span><h2>{{ $roadmap->title }}</h2>
                    @if ($roadmap->description)<details class="study-roadmap-description"><summary>Objetivo desta trilha</summary><div class="rich-text-content rich-text-readonly">{!! $roadmap->description !!}</div></details>@endif
                    <p class="muted">{{ $roadmap->completed_topics_count }} de {{ $roadmap->topics_count }} tópicos concluídos @if ($roadmap->due_date) · Prazo: {{ $roadmap->due_date->format('d/m/Y') }}@endif</p>
                    <progress max="100" value="{{ $roadmap->progress() }}" aria-label="Progresso do roadmap"></progress><small>{{ $roadmap->progress() }}%</small>
                </div>
                @if ($canManage)<div class="actions"><button type="button" class="button secondary" wire:click="openForm('roadmap', {{ $roadmap->id }})">Editar roadmap</button>
                    <button type="button" class="button" wire:click="openForm('step', null, {{ $roadmap->id }})">+ Nova etapa</button></div>@endif
            </section>
            <div class="study-toolbar">
                <p class="muted">Seu caminho de aprendizado, conectado passo a passo.</p>
                <div class="actions"><label class="sr-only" for="study-display">Visualização do roadmap</label><select id="study-display" wire:model.live="display"><option value="canvas">Quadro visual</option><option value="list">Lista</option></select>
                    <a wire:navigate href="{{ route('studies.library', [$workspace, 'roadmap' => $roadmap->id]) }}">Materiais</a></div>
            </div>
            @if ($display === 'list')
                @include('studies.list')
            @else
                @include('studies.canvas')
            @endif
        @else
            <div class="study-filters"><label>Buscar roadmap<input type="search" wire:model.live.debounce.300ms="search" placeholder="O que você quer aprender?"></label>
                <label>Status<select wire:model.live="statusFilter"><option value="">Todos</option>@foreach (\App\Enums\StudyStatus::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label></div>
            @include('studies.roadmap-cards')
        @endif
    @elseif ($section === 'library')
        <div class="study-filters"><label>Buscar material<input type="search" wire:model.live.debounce.300ms="search" placeholder="Título do material"></label>
            <label>Roadmap<select wire:model.live="roadmapId"><option value="">Todos os roadmaps</option>@foreach ($roadmapOptions as $option)<option value="{{ $option->id }}">{{ $option->title }}</option>@endforeach</select></label>
            <label>Tipo<select wire:model.live="materialType"><option value="">Todos os tipos</option><option value="link">Links</option><option value="file">Arquivos</option><option value="pdf">PDFs</option><option value="image">Imagens</option></select></label>
            <label>Status<select wire:model.live="materialStatus"><option value="">Todos os status</option>@foreach (\App\Enums\StudyMaterialStatus::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label></div>
        <div class="study-card-grid">
            @forelse ($materials as $material)
                <article class="panel study-material" wire:key="study-material-{{ $material->id }}">
                    <div class="study-title-row"><span class="tag">{{ $material->path ? strtoupper(pathinfo($material->original_name, PATHINFO_EXTENSION)) : 'LINK' }}</span><span class="tag">{{ $material->status->label() }}</span></div>
                    <h2>{{ $material->title }}</h2><p class="muted">{{ $material->roadmap?->title ?? 'Biblioteca do ambiente' }} @if ($material->topic) · {{ $material->topic->title }}@endif</p>
                    @if ($material->path)<p class="muted">{{ $material->original_name }} · {{ number_format($material->size / 1024, 0, ',', '.') }} KB</p>@endif
                    <div class="actions">
                        @if ($material->url)<a class="button secondary" href="{{ $material->url }}" target="_blank" rel="noopener noreferrer">Abrir referência ↗</a>
                        @else
                            @if (in_array($material->mime, ['application/pdf', 'image/png', 'image/jpeg', 'image/webp']))<a class="button secondary" href="{{ route('studies.file', [$workspace, $material->id, 'preview' => 1]) }}" target="_blank" rel="noopener">Visualizar</a>@endif
                            <a href="{{ route('studies.file', [$workspace, $material->id]) }}">Baixar</a>
                        @endif
                        @if ($canManage)<button type="button" class="text-button" wire:click="openForm('material', {{ $material->id }})">Editar</button>
                            <button type="button" class="text-button danger" wire:click="deleteRecord('material', {{ $material->id }})" data-confirm="O material e seu arquivo, se houver, serão excluídos definitivamente." data-confirm-title="Excluir material?" data-confirm-subject="{{ $material->title }}" data-confirm-label="Excluir material" wire:loading.attr="disabled">Excluir</button>@endif
                    </div>
                </article>
            @empty<div class="panel study-empty">Guarde os arquivos e as referências que acompanham seu aprendizado.</div>@endforelse
        </div>
        {{ $materials->links() }}
    @elseif ($section === 'history')
        <div class="study-filters"><label>Buscar sessão<input type="search" wire:model.live.debounce.300ms="search" placeholder="Tópico ou observação"></label>
            <label>Roadmap<select wire:model.live="roadmapId"><option value="">Todos os roadmaps</option>@foreach ($roadmapOptions as $option)<option value="{{ $option->id }}">{{ $option->title }}</option>@endforeach</select></label></div>
        <section class="panel study-history">
            @forelse ($sessions as $session)
                <article class="study-session" wire:key="study-session-{{ $session->id }}">
                    <div><time datetime="{{ $session->studied_on->toDateString() }}">{{ $session->studied_on->format('d/m/Y') }}</time><span class="tag">{{ $session->minutes ? $session->minutes.' min' : 'Estudo registrado' }}</span>
                        <h3>{{ $session->topic_title }}</h3><p class="muted">{{ $session->roadmap->title }}</p>
                        @if ($session->notes)<p class="study-session-notes">{{ $session->notes }}</p>@endif</div>
                    @if ($canManage)<button type="button" class="text-button danger" wire:click="deleteRecord('session', {{ $session->id }})" data-confirm="Este registro de estudo será excluído. O tópico será preservado." data-confirm-title="Excluir sessão?" data-confirm-subject="{{ $session->topic_title }}" data-confirm-label="Excluir sessão" wire:loading.attr="disabled">Excluir</button>@endif
                </article>
            @empty<p class="muted">Cada sessão conta. Abra um tópico e registre o que estudou hoje.</p>@endforelse
        </section>
        {{ $sessions->links() }}
    @endif
    @if ($formKind)
        @include('studies.form')
    @endif
</div>
