@extends('layouts.app')

@section('title', 'Новый пароль — Global Parts')

@section('content')
    @include('components.header')
    @include('components.header-mini')

    <div id="forgot-password-wrapper">
        <div class="auth-card">
            <div class="auth-icon">🔑</div>
            <h2 class="auth-title">Новый пароль</h2>
            <p class="auth-subtitle">Введите новый пароль для вашего аккаунта</p>

            @if (Session::has('message'))
                <div class="auth-alert auth-alert-success">
                    {{ Session::get('message') }}
                </div>
            @endif

            <form action="{{ route('password.update') }}" method="POST">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ request('email') }}">

                <div class="auth-field">
                    <label class="auth-label">Email</label>
                    <input type="email" name="email" class="auth-input @error('email') is-invalid @enderror"
                           value="{{ request('email') }}" readonly>
                    @error('email')
                        <div class="auth-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="auth-field">
                    <label class="auth-label">Новый пароль</label>
                    <input type="password" name="password" class="auth-input @error('password') is-invalid @enderror"
                           placeholder="Минимум 6 символов">
                    @error('password')
                        <div class="auth-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="auth-field">
                    <label class="auth-label">Подтвердите пароль</label>
                    <input type="password" name="password_confirmation" class="auth-input"
                           placeholder="Повторите пароль">
                </div>

                <button type="submit" class="auth-btn">Сохранить пароль</button>
                <a href="{{ route('login') }}" class="auth-link">← Вернуться ко входу</a>
            </form>
        </div>
    </div>

    @include('components.footer')
    @include('components.footer-bar-mini')
@endsection

@push('styles')
<style>
    #forgot-password-wrapper {
        min-height: calc(100vh - 130px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px 16px;
        background: #f5f7fa;
    }
    .auth-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 32px rgba(0,0,0,0.10);
        padding: 48px 40px 40px;
        max-width: 440px;
        width: 100%;
        text-align: center;
    }
    .auth-icon { font-size: 48px; margin-bottom: 12px; }
    .auth-title { font-size: 1.6rem; font-weight: 700; color: #1a1a2e; margin-bottom: 8px; }
    .auth-subtitle { color: #888; font-size: 0.92rem; margin-bottom: 28px; }
    .auth-alert { border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 0.92rem; text-align: left; }
    .auth-alert-success { background: #e6f9f0; color: #1a7f4b; }
    .auth-field { text-align: left; margin-bottom: 20px; }
    .auth-label { display: block; font-size: 0.88rem; font-weight: 600; color: #444; margin-bottom: 6px; }
    .auth-input { width: 100%; padding: 11px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.97rem; outline: none; box-sizing: border-box; transition: border-color 0.2s; }
    .auth-input:focus { border-color: #1565C0; box-shadow: 0 0 0 3px rgba(21,101,192,0.1); }
    .auth-input.is-invalid { border-color: #dc3545; }
    .auth-input[readonly] { background: #f8f9fa; color: #888; }
    .auth-error { color: #dc3545; font-size: 0.82rem; margin-top: 4px; }
    .auth-btn { width: 100%; background: #1565C0; color: #fff; border: none; border-radius: 8px; padding: 13px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s; margin-bottom: 16px; }
    .auth-btn:hover { background: #0d47a1; }
    .auth-link { display: block; color: #1565C0; font-size: 0.9rem; text-decoration: none; text-align: center; }
    .auth-link:hover { text-decoration: underline; }
    @media (max-width: 580px) {
        #forgot-password-wrapper { min-height: calc(100vh - 65px); padding: 12px 16px; align-items: flex-start; }
        .auth-card { padding: 32px 20px 28px; }
        .auth-title { font-size: 1.3rem; }
    }
</style>
@endpush