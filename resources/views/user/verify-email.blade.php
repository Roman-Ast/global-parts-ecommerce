@extends('layouts.app')

@section('title', 'Войти')
   
@section('content')

    <div class="alert alert-info" role="alert">
        Спасибо за регистрацию! Мы отправили ссылка на указанный Вами почтовый ящик для завершения регистрации.
    </div>
    <div>
        Не получили ссылку?
        <form action="" method="POST">
            @csrf
            <button type="submit" class="btn btn-link ps-0">Отправить повторно ссылку</button>
        </form>
    </div>

@endsection