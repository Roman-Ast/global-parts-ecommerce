@extends('layouts.app')

@section('title', 'Ничего не найдено')
   
@section('content')
@include('components.header')

    <div    class="container nothing-found-wrapper">
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