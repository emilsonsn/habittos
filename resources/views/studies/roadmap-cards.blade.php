<div class="study-card-grid">
    @forelse ($roadmaps as $item)
        <article class="panel study-roadmap-card" style="--study-color: {{ $item->color }}" wire:key="study-roadmap-{{ $item->id }}">
            <span class="tag">{{ $item->status->label() }}</span>
            <h2><a wire:navigate href="{{ route('studies.roadmaps', [$workspace, 'roadmap' => $item->id]) }}">{{ $item->title }}</a></h2>
            <p class="muted">{{ $item->completed_topics_count }} de {{ $item->topics_count }} tópicos concluídos @if ($item->due_date) · {{ $item->due_date->format('d/m/Y') }}@endif</p>
            <progress max="100" value="{{ $item->progress() }}" aria-label="Progresso de {{ $item->title }}"></progress><small>{{ $item->progress() }}%</small>
            <a wire:navigate class="study-card-link" href="{{ route('studies.roadmaps', [$workspace, 'roadmap' => $item->id]) }}">Continuar aprendendo →</a>
        </article>
    @empty<div class="panel study-empty">Nenhum roadmap por aqui ainda. Escolha o que quer aprender e organize seus primeiros passos.</div>@endforelse
</div>
{{ $roadmaps->links() }}
