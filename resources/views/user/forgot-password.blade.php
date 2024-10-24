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
        <form action="{{ route('password.email') }}" method="POST">

            @csrf <!-- {{ csrf_field() }} -->

              <div class="form-group">
                <label for="exampleInputEmail">Email</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" id="exampleInputEmail" aria-describedby="emailHelp" placeholder="Введите ваш email">
                @error('email')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              
              <button type="submit" class="btn btn-primary">Восстановить пароль</button>
         
              <a href="{{ route('home') }}">На главную</a>
          </form>
    </div>
    


@endsection