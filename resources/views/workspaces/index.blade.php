@extends('layout')
@section('title', 'Home')
@section('content')
    <section class="home-page">
        <header class="page-heading">
            <div>
                <span class="eyebrow">UM OLHAR PARA OS SEUS PLANOS</span>
                <h1>{{ $greeting }}, {{ auth()->user()->name }}<span class="heading-dot">.</span></h1>
                <p>{{ $activeWorkspace ? 'Acompanhe o que importa no ambiente ' . $activeWorkspace->name . '.' : 'Faça espaço para os seus próximos passos.' }}</p>
            </div>
        </header>
        <div class="metrics home-metrics" aria-label="Indicadores do ambiente">
            <article class="metric">
                <h2 class="metric-label">Investimentos acumulados <span aria-hidden="true">◇</span></h2>
                <strong>{{ $canViewFinance ? \App\Services\Money::format($investmentBalance) : '—' }}</strong>
                <small>{{ !$canViewFinance ? ($activeWorkspace ? 'Sem acesso ao financeiro' : 'Selecione ou crie um ambiente') : 'Aportes menos retiradas · Todo o histórico do ambiente' }}</small>
            </article>
            @foreach (range(2, 4) as $indicator)
                <article class="metric metric-placeholder">
                    <h2 class="metric-label">Indicador {{ $indicator }} <span aria-hidden="true">◇</span></h2>
                    <strong>—</strong>
                    <small>Em breve</small>
                </article>
            @endforeach
        </div>
        <p class="muted home-note">Escolha um ambiente na barra lateral para começar. Para criar outro, use o botão + ao lado do
            seletor.</p>
    </section>
@endsection
