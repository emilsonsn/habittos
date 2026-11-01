@extends('layout')
@section('title', $goalSection->label())
@section('content')
    @if ($goalSection === \App\Enums\GoalSection::Dashboard)
        <livewire:goals-dashboard :workspace-id="$workspace->id" />
    @else
        <livewire:goals-board :workspace-id="$workspace->id" :period="$goalSection->value" />
    @endif
@endsection
