@extends('layout')
@section('title', \App\Enums\WorkspaceModule::Routine->label())
@section('content')
    <livewire:routine-board :workspace-id="$workspace->id" />
@endsection
