@extends('layout')
@section('title', 'Estudos · '.$studySection->label())
@section('content')
    <livewire:study-area :workspace-id="$workspace->id" :section="$studySection->value" />
@endsection
