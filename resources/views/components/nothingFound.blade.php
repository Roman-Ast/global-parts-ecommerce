@extends('layouts.app')

@section('title', 'Ничего не найдено')
   
@section('content')

<div id="nothing-found-wrapper" class="container">
    @include('components.header')

    <div id="not-found-wrapper">
        <h4 id="not-found-header">
            <i>Проверьте правильность введенных данных...</i>
        </h4>
        <div id="not-found-img-container">
            <img src="/images/Not_found.jpg" alt="not-found">
        </div>
    </div>
</div>

@include('components.footer')
@endsection