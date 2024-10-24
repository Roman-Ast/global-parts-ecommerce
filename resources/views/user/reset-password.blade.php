@extends('layouts.app')

@section('title', 'Восстановление пароля')
   
@section('content')

@if (Session::has('message'))

        <div class="alert {{ Session::get('class') }}" style="align-text:center;" id>
            <div style="display:flex;justify-content:flex-end;" class="close-flash">
                &times;
            </div>
            {{ Session::get('message') }}
        </div>
    
  @endif

<div id="register-wrapper">
    <form action="{{ route('password.update') }}" method="POST">

      @csrf <!-- {{ csrf_field() }} -->

        <input type="hidden" name="token" value="{{ $token }}">

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
        <div class="form-group">
            <label for="exampleInputPassword1">Подтвердите пароль</label>
            <input type="password" name="confirm-password" class="form-control" id="exampleInputPassword1" placeholder="Подтвердите пароль">
        </div>
        
        <button type="submit" class="btn btn-primary">Сбросить пароль</button>
        <a href="{{ route('home') }}">На главную</a>
    </form>
</div>


@endsection