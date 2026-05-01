@extends('layouts.app') {{-- Или какой у тебя главный лейаут --}}

@section('content')
    <div class="container-fluid">
        @livewire('admin.kanban-board')
    </div>
@endsection