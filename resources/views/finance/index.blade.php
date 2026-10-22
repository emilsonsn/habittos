@extends('layout')
@section('title', 'Financeiro')
@section('content')
    <livewire:finance-dashboard :workspace-id="$workspace->id" :month="$month" />
@endsection
