@extends('layouts.app')

@section('title', 'Главная')
    


@section('content')
    <div id="shadow">
        <div class="d-flex justify-content-center">
            <div class="spinner-border text-primary" style="width: 6rem; height: 6rem;" role="status">
            <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        <div id="loading" class="d-flex justify-content-center mt-5">Выполняется проценка складов... это может занять несколько секунд, пожалуйста ожидайте...</div>
    </div>

    <div class="alert {{ Session::get('class') }}" style="align-text:center;" id>
        <div style="display:flex;justify-content:flex-end;" class="close-flash">
            &times;
        </div>
        {{ Session::get('message') }}
    </div>
     
    @include('components.header')
    
    <div id="main-container" class="container">
        
    </div>

    @include('components.footer')
@endsection















