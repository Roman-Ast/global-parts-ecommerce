@extends('layouts.app')

@section('title', 'Гараж')
    


@section('content')
    <div class="alert {{ Session::get('class') }}" style="align-text:center;" id>
        <div style="display:flex;justify-content:flex-end;" class="close-flash">
            &times;
        </div>
        {{ Session::get('message') }}
    </div>
     
    @include('components.header')
    
    <div id="main-container" class="container garage">
        <a href="/garage/create"><button class="btn btn-primary">Добавить авто</button></a>

        <table class="table">
            <thead>
              <tr>
                <th scope="col">#</th>
                <th scope="col">Модель авто</th>
                <th scope="col">Год выпуска</th>
                <th scope="col">Винкод</th>
                <th scope="col">Номер авто</th>
                <th scope="col">Имя владельца</th>
                <th scope="col">Телефон владельца</th>
                <th scope="col">Примечание</th>
              </tr>
            </thead>
            <tbody>
                @foreach ($cars_in_garage as $car)
                <tr>
                    <th scope="row">{{ $car->id }}</th>
                    <td>{{ $car->model }}</td>
                    <td>{{ $car->year }}</td>
                    <td>{{ $car->vincode }}</td>
                    <td>{{ $car->license }}</td>
                    <td>{{ $car->owner_name }}</td>
                    <td>{{ $car->owner_phone }}</td>
                    <td>{{ $car->note }}</td>
                  </tr>
                @endforeach
            </tbody>
          </table>
    </div>

    @include('components.footer')
@endsection















