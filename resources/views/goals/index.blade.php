@extends('layout')
@section('title', 'Metas')
@section('content')
    <livewire:goals-board :workspace-id="$workspace->id" />
@endsection
