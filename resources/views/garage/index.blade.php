@extends('layouts.app')

@section('title', 'Гараж')



@section('content')
    @include('components.header')
    @include('components.header-mini')

    @if (session()->has('message'))
    <div class="alert {{ Session::get('class') }}" style="align-text:center;" id>
      <div style="display:flex;justify-content:flex-end;" class="close-flash">
          &times;
      </div>
      {{ Session::get('message') }}
    </div>
    @endif

    <div class="container garage">
        <a href="/garage/create">
          <button class="btn btn-primary mb-3">Добавить авто</button>
        </a>

        {{-- Десктоп: таблица --}}
        <table class="table d-none d-md-table" id="garage-list">
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
                <form action="/garage/destroy" method="POST">
                  <input type="hidden" value="{{ $car->id }}" name="car_id">
                  @csrf
                <tr>
                    <th scope="row">{{ $car->id }}</th>
                    <td>{{ $car->model }}</td>
                    <td>{{ $car->year }}</td>
                    <td>{{ $car->vincode }}</td>
                    <td>{{ $car->licence }}</td>
                    <td>{{ $car->owner_name }}</td>
                    <td>{{ $car->owner_phone }}</td>
                    <td>{{ $car->note }}</td>
                    <td><button class="btn btn-sm btn-danger">&times;</button></td>
                  </tr>
                </form>
                @endforeach
            </tbody>
          </table>

        {{-- Мобилка: карточки вместо широкой таблицы --}}
        <div class="d-md-none">
            @foreach ($cars_in_garage as $car)
                <div class="border rounded-3 p-3 mb-3 bg-white shadow-sm">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="fw-bold">{{ $car->model }} · {{ $car->year }}</div>
                        <form action="/garage/destroy" method="POST" class="m-0">
                            <input type="hidden" value="{{ $car->id }}" name="car_id">
                            @csrf
                            <button class="btn btn-sm btn-danger">&times;</button>
                        </form>
                    </div>
                    <div class="small text-muted">Винкод: <span class="text-dark">{{ $car->vincode }}</span></div>
                    <div class="small text-muted">Номер авто: <span class="text-dark">{{ $car->licence }}</span></div>
                    <div class="small text-muted">Владелец: <span class="text-dark">{{ $car->owner_name }}</span></div>
                    <div class="small text-muted">Телефон: <span class="text-dark">{{ $car->owner_phone }}</span></div>
                    @if($car->note)
                        <div class="small text-muted">Примечание: <span class="text-dark">{{ $car->note }}</span></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    @include('components.footer-bar-mini')
    @include('components.footer')
@endsection
