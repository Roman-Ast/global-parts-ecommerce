@extends('layouts.app')

@section('title', 'Войти')
   
@section('content')
@if (Session::has('message'))

    <div class="alert {{ Session::get('class') }}" style="align-text:center;" id>
        <div style="display:flex;justify-content:flex-end;" class="close-flash">
            &times;
        </div>
        {{ Session::get('message') }}
    </div>
    
    @endif
<div id="login-wrapper">
    <form action="{{ route('user.login') }}" method="POST">
        <div class="form-group">
          <label for="exampleInputLogin">Логин</label>
          <input type="text" class="form-control" id="exampleInputLogin" aria-describedby="emailHelp" placeholder="Введите ваш логин">
        </div>
        <div class="form-group">
          <label for="exampleInputPassword1">Пароль</label>
          <input type="password" class="form-control" id="exampleInputPassword1" placeholder="Пароль">
        </div>
        <button type="submit" class="btn btn-primary">Войти</button>
        <a href="{{ route('home') }}">На главную</a>
    </form>
</div>


@endsection