<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Таблица физически отсутствовала (модель App\Models\LeadRequest и
 * WhatsappMessageObserver её использовали, но миграции в репозитории не было —
 * см. разбор с Романом 2026-09-13). Заодно расширена под то, что раньше
 * склеивалось в одну строку car_model: марка/модель/год отдельными колонками
 * (нужно для складской аналитики — "что чаще спрашивают по каким авто"),
 * плюс source (site/2gis), скопированный с лида на момент создания — чтобы
 * анализ по источнику не требовал джойна на whatsapp_leads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_lead_id')->constrained('whatsapp_leads')->cascadeOnDelete();
            $table->string('source', 50)->nullable()->index(); // site / 2gis — копия с лида на момент создания заявки
            $table->string('vin', 20)->nullable()->index(); // 17 символов VIN либо более короткий frame-номер (японские авто)
            $table->string('brand', 100)->nullable();
            $table->string('car_model', 150)->nullable();
            $table->string('car_year', 10)->nullable(); // строка, не int — иногда это диапазон ("2015-2018") или неточно распознанное значение
            $table->text('raw_request')->nullable(); // накопленный текст сообщений клиента, из которых собрана заявка
            $table->json('parts_json')->nullable(); // [{name, side, position}, ...]
            $table->string('status', 30)->default('pending')->index(); // pending / answered / closed_won / closed_lost
            $table->decimal('deal_sum', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_requests');
    }
};
