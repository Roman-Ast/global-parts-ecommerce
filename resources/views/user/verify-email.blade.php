@extends('layouts.app')

@section('title', 'Войти')
   
@section('content')
    @include('components.header')
    @include('components.header-mini')

    <div id="verify-email-wrapper" class="container">
        <h5>На адрес электронной почты, указанной при регистрации, было отправлено письмо с ссылкой для подтверждения вашего E-Mail</h5>

        <div style="margin-top: 20px">
            <div><i>Не получили ссылку?</i></div>
            <form action="{{ route('verification.send') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-link ps-0">Отправить повторно ссылку</button>
            </form>
        </div>
    </div>
    

    @include('components.footer')
    @include('components.footer-bar-mini')
@endsection