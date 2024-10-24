@extends('layouts.app')

@section('title', 'Главная')
    


@section('content')

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















