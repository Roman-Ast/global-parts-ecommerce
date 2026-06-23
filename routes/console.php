<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('kaspi:sync', function () {
    $this->call('kaspi:match');
    $this->call('kaspi:generate-xml');
})->describe('Матчинг + генерация фида каспи');

// Расписание — не трогаем пока
Schedule::command('prices:fetch')->everyTenMinutes();
