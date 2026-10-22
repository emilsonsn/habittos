@extends('layout')
@section('title', 'Configurações')
@section('content')
    <livewire:workspace-settings :workspace-id="$workspace->id" />
@endsection
