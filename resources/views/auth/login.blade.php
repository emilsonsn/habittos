@extends('layout')
@section('title', 'Entrar')
@section('content')
    <div class="auth-card"><span class="eyebrow">BOM TER VOCÊ AQUI</span>
        <h1>Seu próximo passo<br>começa aqui.</h1>
        <p class="muted">Entre para cuidar dos seus planos.</p>
        <form method="POST" action="{{ route('login') }}" class="form-stack">@csrf
            <label>E-mail<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required
                    autofocus></label>
            <label>Senha<input type="password" name="password" autocomplete="current-password" required></label>
            <button class="button">Entrar no meu espaço ↗</button>
        </form>
        <p class="muted">Primeira vez por aqui? <a wire:navigate href="{{ route('register') }}">Criar minha conta</a></p>
    </div>
@endsection
