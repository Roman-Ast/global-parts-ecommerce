@extends('layouts.app')

@section('title', 'Подтверждение Email — Global Parts')

@section('content')
    @include('components.header')
    @include('components.header-mini')

    <div id="verify-email-wrapper">
        <div class="verify-card">
            <div class="verify-icon">✉️</div>
            <h2 class="verify-title">Подтвердите ваш Email</h2>
            <p class="verify-text">
                Мы отправили письмо со ссылкой для подтверждения на вашу электронную почту.<br>
                Перейдите по ссылке в письме, чтобы завершить регистрацию.
            </p>

            @if(session('message'))
                <div class="verify-success">
                    ✅ {{ session('message') }}
                </div>
            @endif

            <div class="verify-resend">
                <p>Не получили письмо?</p>
                <form action="{{ route('verification.send') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn-resend">Отправить повторно</button>
                </form>
            </div>

            <div class="verify-hint">
                Проверьте папку <strong>Спам</strong>, если письмо не приходит в течение нескольких минут.
            </div>
        </div>
    </div>

    @include('components.footer')
    @include('components.footer-bar-mini')
@endsection

@push('styles')
<style>
    #verify-email-wrapper {
        min-height: calc(100vh - 130px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px 16px;
        background: #f5f7fa;
    }

    .verify-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 32px rgba(0,0,0,0.10);
        padding: 48px 40px 40px;
        max-width: 480px;
        width: 100%;
        text-align: center;
    }

    .verify-icon {
        font-size: 56px;
        margin-bottom: 16px;
    }

    .verify-title {
        font-size: 1.6rem;
        font-weight: 700;
        color: #1a1a2e;
        margin-bottom: 16px;
    }

    .verify-text {
        color: #555;
        font-size: 0.97rem;
        line-height: 1.6;
        margin-bottom: 28px;
    }

    .verify-success {
        background: #e6f9f0;
        color: #1a7f4b;
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 24px;
        font-size: 0.95rem;
    }

    .verify-resend {
        border-top: 1px solid #f0f0f0;
        padding-top: 24px;
        margin-bottom: 20px;
    }

    .verify-resend p {
        color: #888;
        font-size: 0.9rem;
        margin-bottom: 12px;
    }

    .btn-resend {
        background: #1565C0;
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 11px 28px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }

    .btn-resend:hover {
        background: #0d47a1;
    }

    .verify-hint {
        font-size: 0.82rem;
        color: #aaa;
        margin-top: 4px;
    }

    @media (max-width: 580px) {
        #verify-email-wrapper {
            min-height: calc(100vh - 65px);
            padding: 8px 16px;
            align-items: flex-start;
            padding-top: 12px;
        }
        .verify-card {
            padding: 32px 20px 28px;
        }
        .verify-title {
            font-size: 1.3rem;
        }
    }
</style>
@endpush