@extends('layout')
@section('title', $section->label())
@section('content')
    <livewire:finance-dashboard :workspace-id="$workspace->id" :month="$month" :section="$section->value" />
@endsection
