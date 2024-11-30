@extends('layouts.app')

@section('title', 'Ошибка сервера')
   
@section('content')

<div id="nothing-found-wrapper" class="container">
    @include('components.header')

    <div class="nothing-found-wrapper">
        <h4 id="not-found-header">
            <i>Кто-то всё сломал... попробуйте еще раз</i>
        </h4>
        <div id="not-found-img-container">
            <img src="/images/host-error.jpeg" alt="host-error">
        </div>
    </div>
</div>

@include('components.footer')
@endsection