<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // Убедись, что API роуты подключены
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Исключаем наш вебхук из проверки CSRF
        $middleware->validateCsrfTokens(except: [
            'api/whatsapp/webhook'
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
    
