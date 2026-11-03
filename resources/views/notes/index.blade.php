@extends('layout')
@section('title', $noteSection === \App\Enums\NoteSection::Dashboard ? 'Dashboard de notas' : $noteSection->label())
@section('content')
    <livewire:notes-library :workspace-id="$workspace->id" :section="$noteSection->value" />
@endsection
