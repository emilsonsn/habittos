@props(['statuses', 'total'])
@php
    $slices = [];
    $segments = [];
    $offset = 0;
    foreach ($statuses as $status) {
        if ($status['count'] === 0) continue;
        $percentage = $status['count'] / max(1, $total) * 100;
        $end = min(100, $offset + $percentage);
        $segments[] = $status['color'].' '.$offset.'% '.$end.'%';
        $slices[] = ['name' => $status['name'], 'value' => $status['count'].' '.($status['count'] === 1 ? 'meta' : 'metas'), 'share' => number_format($percentage, 1, ',', '.').'%', 'end' => $end];
        $offset = $end;
    }
@endphp
<div class="goal-status-visual">
    <div class="goal-status-donut" role="img" aria-label="Distribuição de {{ $total }} metas por status. Quantidades e percentuais na legenda."
        style="background: {{ $total ? 'conic-gradient('.implode(', ', $segments).')' : 'var(--line)' }}"
        x-data="{ active: null }" data-slices="{{ json_encode($slices) }}"
        @pointermove="active = HabittosCategoryPie.at($event, JSON.parse($el.dataset.slices))"
        @pointerleave="active = null" @pointercancel="active = null">
        <div class="goal-donut-center" @pointermove.stop="active = null"><strong>{{ $total }}</strong><span>{{ $total === 1 ? 'meta no ano' : 'metas no ano' }}</span></div>
        <div class="category-tooltip" x-cloak x-show="active" aria-hidden="true">
            <span class="category-tooltip-name" x-text="active?.name"></span><strong x-text="active?.value"></strong><small x-text="active?.share"></small>
        </div>
    </div>
    <ul class="goal-chart-legend" aria-label="Quantidade de metas por status">
        @foreach ($statuses as $status)
            <li><span class="goal-chart-dot" style="background: {{ $status['color'] }}" aria-hidden="true"></span>
                <span>{{ $status['name'] }}</span><strong>{{ $status['count'] }}</strong><small>{{ number_format($status['count'] / max(1, $total) * 100, 1, ',', '.') }}%</small>
            </li>
        @endforeach
    </ul>
</div>
