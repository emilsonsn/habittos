@extends('layout')
@section('title', 'Criar conta')
@section('content')
    <div class="auth-card"><span class="eyebrow">UM NOVO COMEÇO</span>
        <h1>Faça espaço<br>para seus planos.</h1>
        <p class="muted">Seu primeiro ambiente será criado junto com a conta.</p>
        <form method="POST" action="{{ route('register') }}" class="form-stack">@csrf
            <label>Seu nome<input name="name" value="{{ old('name') }}" maxlength="120" autocomplete="name" required
                    autofocus></label>
            <label>E-mail<input type="email" name="email" value="{{ old('email') }}" autocomplete="email"
                    required></label>
            <label>Senha<input type="password" name="password" minlength="10" autocomplete="new-password" required><small>Ao
                    menos 10 caracteres, com letras e números.</small></label>
            <label>Confirme a senha<input type="password" name="password_confirmation" autocomplete="new-password"
                    required></label>
            <button class="button">Criar minha conta ↗</button>
        </form>
        <p class="muted">Já tem conta? <a wire:navigate href="{{ route('login') }}">Entrar</a></p>
    </div>
@endsection
