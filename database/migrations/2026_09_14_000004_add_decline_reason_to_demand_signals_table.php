<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Закрытая таксономия причин отказа — выведена вручную Романом из реальных
 * чатов 2026-09-14 (не сгенерирована LLM), чтобы не получить россыпь из
 * тысячи свободных формулировок. Заполняется только когда outcome=declined —
 * для outcome=silent причина и так ясна (молчание), для bought/pending не
 * применимо.
 *
 * Ключевой для склада сигнал — 'no_stock_wont_wait': клиент купил бы деталь
 * прямо сейчас, если бы она была на складе. Остальные причины нужны, чтобы
 * ЕГО не размывать шумом (дорого/деталь физически недоступна/передумал —
 * ни одну из этих продаж склад бы не спас).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demand_signals', function (Blueprint $table) {
            $table->enum('decline_reason', [
                'no_stock_wont_wait',   // нет в наличии, ждать не готов — купил бы прямо сейчас
                'in_stock_too_expensive', // есть в наличии, но дорого
                'part_not_found',       // такой детали у нас вообще нет/не можем найти
                'changed_mind',         // передумал/деталь больше не актуальна — цена и склад ни при чём
            ])->nullable()->after('outcome')->index();
        });
    }

    public function down(): void
    {
        Schema::table('demand_signals', function (Blueprint $table) {
            $table->dropColumn('decline_reason');
        });
    }
};
