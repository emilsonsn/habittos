@extends('layout')
@section('title', 'Pessoas e acessos')
@section('content')
    <livewire:members-manager :workspace-id="$workspace->id" />
@endsection
