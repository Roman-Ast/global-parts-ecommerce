<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppWebhookController;

// Наш главный роут для приема сообщений
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);
