@props(['totals'])
@php
    $positiveTotals = $totals->filter(fn ($amount) => $amount > 0);
    $total = $positiveTotals->sum();
    $colors = ['#8584dc', '#6abda5', '#d9ad90', '#c487b5', '#7cb5dc', '#d2bc6a', '#aa9ad7', '#89b984'];
    $segments = [];
    $slices = [];
    $offset = 0;
    foreach ($positiveTotals as $name => $amount) {
        $color = $colors[count($slices) % count($colors)];
        $percentage = $amount / $total * 100;
        $end = min(100, $offset + $percentage);
        $segments[] = $color.' '.$offset.'% '.$end.'%';
        $value = \App\Services\Money::format($amount);
        $share = number_format($percentage, 1, ',', '.').'%';
        $slices[] = compact('name', 'amount', 'color', 'percentage', 'end', 'value', 'share');
        $offset = $end;
    }
@endphp
@if ($total > 0)
    <div class="category-distribution">
        <figure class="category-chart">
            <div class="category-pie" role="img" aria-label="Gráfico de pizza das despesas por categoria. Valores e percentuais na legenda."
                x-data="{ active: null }" data-slices="{{ json_encode($slices) }}"
                @pointermove="active = HabittosCategoryPie.at($event, JSON.parse($el.dataset.slices))"
                @pointerleave="active = null" @pointercancel="active = null"
                style="background: conic-gradient({{ implode(', ', $segments) }})">
                <div class="category-tooltip" x-cloak x-show="active" aria-hidden="true">
                    <span class="category-tooltip-name" x-text="active?.name"></span>
                    <strong x-text="active?.value"></strong>
                    <small x-text="active?.share"></small>
                </div>
            </div>
            <figcaption>Total de despesas <strong>{{ \App\Services\Money::format($total) }}</strong></figcaption>
        </figure>
        <ul class="category-legend" aria-label="Valores das despesas por categoria">
            @foreach ($slices as $slice)
                <li>
                    <span class="category-swatch" style="background: {{ $slice['color'] }}" aria-hidden="true"></span>
                    <span class="category-legend-name">{{ $slice['name'] }}</span>
                    <span class="category-legend-value"><strong>{{ \App\Services\Money::format($slice['amount']) }}</strong>
                        <small>{{ number_format($slice['percentage'], 1, ',', '.') }}%</small></span>
                </li>
            @endforeach
        </ul>
    </div>
@else
    <p class="muted">As categorias aparecem conforme você registra despesas.</p>
@endif
