<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Просьба Романа 2026-09-18: клиенты иногда пишут/звонят через Kaspi, но
 * оформляют заказ напрямую (мимо магазина Kaspi) — комиссию в этом случае
 * реально не платим, но канал привлечения всё равно "Kaspi", это ценный
 * атрибуционный сигнал, терять который не хочется.
 *
 * Сознательно НЕ отдельный sale_channel ("kaspi_no_commission" и т.п.) —
 * это задвоило бы статистику по каналам везде, где она сейчас просто
 * группируется по sale_channel='kaspi' (пришлось бы задним числом
 * "склеивать" два значения обратно в каждом отчёте). Булев флаг на самом
 * заказе решает и это, и заодно вторую находку по ходу разбора: в
 * changeStatus() есть автоматика "после статуса 'выдано' ждём оплату от
 * Kaspi на счёт Kaspi Pay", завязанная на тот же sale_channel==='kaspi' —
 * без флага она продолжала бы ждать несуществующий маркетплейс-платёж по
 * заказу, который на самом деле уже оплачен напрямую.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'kaspi_bypassed')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->boolean('kaspi_bypassed')->default(false)->after('sale_channel');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'kaspi_bypassed')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('kaspi_bypassed');
            });
        }
    }
};
