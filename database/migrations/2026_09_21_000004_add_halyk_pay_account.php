<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Роман 2026-09-21: добавляем Ozon и Halyk Market как каналы продаж, по
 * аналогии с Kaspi — при выдаче заказа деньги должны автоматически
 * садиться на счёт (см. AdminPanelController::AUTO_PAYOUT_MARKETPLACES).
 * Для Ozon Роман уже указал существующий "Рома Kaspi Pay" при регистрации
 * на маркетплейсе — отдельный счёт не нужен. Для Halyk Market нужен НОВЫЙ
 * счёт "Halyk Pay" — "Рома Халык" уже занят личным счётом Романа в Halyk
 * Bank, смешивать с маркетплейсом нельзя.
 *
 * updateOrInsert по имени — безопасно запускать повторно.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('accounts')->updateOrInsert(
            ['name' => 'Halyk Pay'],
            [
                'currency' => 'KZT',
                'is_active' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        // Осознанно пусто — новый постоянный счёт, не разовая
        // корректировка данных, откатывать не нужно.
    }
};
