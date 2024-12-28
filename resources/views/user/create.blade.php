@extends('layouts.app')

@section('title', 'Регистрация')
   
@section('content')
  @if (Session::has('message'))
    <div class="alert {{ Session::get('class') }}" style="align-text:center;" id>
      <div style="display:flex;justify-content:flex-end;" class="close-flash">
          &times;
      </div>
      {{ Session::get('message') }}
    </div>
  @endif
    
  @include('components.header')
  @include('components.header-mini')

  <div id="register-wrapper">
    <form action="{{ route('user.store') }}" method="POST">

      @csrf <!-- {{ csrf_field() }} -->

        <div class="form-group">
          <label for="exampleInputLogin">Имя</label>
          <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" id="exampleInputName" aria-describedby="emailHelp" placeholder="Введите ваше имя">
          @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>
        <div class="form-group">
            <label for="exampleInputPhone">Телефон</label>
            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" id="exampleInputLogin" aria-describedby="emailHelp" placeholder="Введите ваш номер телефона">
            @error('phone')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
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
        <div class="form-check">
          <input type="checkbox" class="form-check-input" id="exampleCheck1">
          <label class="form-check-label" for="exampleCheck1">Запомнить меня</label>
        </div>
        <button type="submit" class="btn btn-primary">Регистрация</button>
        <a href="{{ route('home') }}">На главную</a>
    </form>
</div>

  @include('components.footer-bar-mini')
@endsection