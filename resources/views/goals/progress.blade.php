<div class="goal-completion">
    <span>{{ number_format((float) $goal->progress_percent, 1, ',', '.') }}%</span>
    <progress value="{{ $goal->progress_percent }}" max="100" aria-label="Progresso de {{ $goal->title }}">{{ $goal->progress_percent }}%</progress>
</div>
