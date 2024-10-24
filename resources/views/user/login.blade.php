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
    <div id="login-wrapper" class="container">
        <form action="{{ route('login.auth') }}" method="POST">

            @csrf <!-- {{ csrf_field() }} -->

              <div class="form-group">
                <label for="exampleInputEmail">Email</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" id="exampleInputEmail" aria-describedby="emailHelp" placeholder="Введите ваш email">
                @error('email')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <div class="form-group">
                <label for="exampleInputPassword1">Пароль</label>
                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" id="exampleInputPassword1" placeholder="Пароль">
                @error('password')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <div class="mb-3 form-check">
                <input type="checkbox" name="remember" id="remember" class="form-check-input">
                <label for="remember" class="form-check-label">
                    Запомнить меня
                </label>
              </div>
              <button type="submit" class="btn btn-primary">Войти</button>
              <a href="{{ route('password.request') }}" class="ms-2">Забыли пароль?</a>
              <a href="{{ route('home') }}">На главную</a>
          </form>
    </div>
    


@endsection