@extends('layouts.app')

@section('title', 'Добавить в гараж')
    


@section('content')
    <div class="alert {{ Session::get('class') }}" style="align-text:center;" id>
        <div style="display:flex;justify-content:flex-end;" class="close-flash">
            &times;
        </div>
        {{ Session::get('message') }}
    </div>
     
    @include('components.header')
    
    <div id="main-container" class="container garage-container">
        <div id="garage-create-wrapper">
        <form action="{{ route('garage.store') }}" method="POST">
            @csrf <!-- {{ csrf_field() }} -->
      
              <div class="form-group">
                <label for="exampleInputLogin">Модель авто</label>
                <input type="text" name="model" class="form-control @error('name') is-invalid @enderror" id="exampleInputName" aria-describedby="emailHelp">
                @error('name')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <div class="form-group">
                  <label for="exampleInputPhone">Год выпуска</label>
                  <input type="text" name="year" class="form-control @error('phone') is-invalid @enderror" id="exampleInputLogin" aria-describedby="emailHelp">
                  @error('phone')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
              </div>
              <div class="form-group">
                <label for="exampleInputEmail">Винкод</label>
                <input type="text" name="vincode" class="form-control @error('email') is-invalid @enderror" id="exampleInputEmail" aria-describedby="emailHelp">
                @error('email')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <div class="form-group">
                <label for="exampleInputPassword1">Госномер авто</label>
                <input type="text" name="licence" class="form-control @error('password') is-invalid @enderror" id="exampleInputPassword1">
                @error('password')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <div class="form-group">
                  <label for="exampleInputPassword1">Имя владельца</label>
                  <input type="text" name="owner_name" class="form-control" id="exampleInputPassword1">
                </div>
              <div class="form-group">
                <label for="exampleCheck1">Телефон владельца</label>
                <input type="text" name="owner_phone" class="form-control" id="exampleInputPassword1">
              </div>
              <div class="form-group">
                <label for="exampleCheck1">Примечание</label>
                <input type="text" name="note" class="form-control" id="exampleInputPassword1">
              </div>
              <input type="hidden" name="user_id" value="{{ auth()->user()->id }}">
              <button type="submit" class="btn btn-primary">Сохранить</button>
        </form>
        </div>
    </div>

    @include('components.footer')
@endsection















