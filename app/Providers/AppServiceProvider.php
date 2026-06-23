<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\URL as UrlFacade;
use Illuminate\Support\ServiceProvider;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('ru');

        if (config('app.env') !== 'local') {
            URL::forceScheme('https');
        }

        \App\Models\WhatsappMessage::observe(\App\Observers\WhatsappMessageObserver::class);
    }
}