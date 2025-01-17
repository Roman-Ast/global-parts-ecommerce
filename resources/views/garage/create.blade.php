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
    
    <div id="garage-new-item-create" class="container ">
        <div id="garage-create-wrapper">
        <form action="{{ route('garage.store') }}" method="POST">
            @csrf <!-- {{ csrf_field() }} -->
      
              <div class="form-group">
                <label for="exampleInputLogin">Марка и/или модель авто</label>
                <input type="text" name="model" class="form-control" required>
              </div>
              <div class="form-group">
                  <label for="exampleInputPhone">Год выпуска</label>
                  <input type="text" name="year" class="form-control" required>
              </div>
              <div class="form-group">
                <label for="exampleInputEmail">Винкод</label>
                <input type="text" name="vincode" class="form-control @error('vincode') is-invalid @enderror" id="exampleInputEmail" aria-describedby="emailHelp">
                @error('vincode')
                  <div class="invalid-feedback">Авто с таким винкодом уже есть в гараже</div>
                @enderror
              </div>
              <div class="form-group">
                <label for="exampleInputPassword1">Госномер авто</label>
                <input type="text" name="licence" class="form-control @error('licence') is-invalid @enderror" id="exampleInputPassword1">
                @error('licence')
                  <div class="invalid-feedback">Авто с таким номером уже есть в гараже</div>
                @enderror
              </div>
              <div class="form-group">
                  <label for="exampleInputPassword1">Имя владельца</label>
                  <input type="text" name="owner_name" class="form-control" id="exampleInputPassword1" required>
                </div>
              <div class="form-group">
                <label for="exampleCheck1">Телефон владельца</label>
                <input type="text" name="owner_phone" class="form-control" id="exampleInputPassword1" required>
              </div>
              <div class="form-group">
                <label for="exampleCheck1">Примечание</label>
                <input type="text" name="note" class="form-control" id="exampleInputPassword1">
              </div>
              <input type="hidden" name="user_id" value="{{ auth()->user()->id }}">
              <button type="submit" class="btn btn-primary" id="garage-new-item">Сохранить</button>
        </form>
        </div>
    </div>
@endsection















