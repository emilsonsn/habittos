@extends('layout')
@section('title', $entry ? 'Editar lançamento' : 'Novo lançamento')
@section('content')
    <livewire:entry-form :workspace-id="$workspace->id" :entry-id="$entry?->id" />
@endsection
