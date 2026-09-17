<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Финальный нормализованный слой для анализа спроса — одна строка на ОДНУ
 * запрошенную деталь с исходом (не на сообщение и не на лида целиком, а именно
 * на позицию: "опору амортизатора спросили — не было в наличии — отказался
 * ждать"). Ради этого весь пайплайн и строился (см. переписку с Романом
 * 2026-09-13 и память demand-signal-source-is-whatsapp-chat-not-site-search) —
 * склад закупается на основе того, что реально спрашивают и не находят.
 *
 * Наполняется НЕ синхронно по каждому сообщению (в отличие от lead_requests) —
 * для этого нужен отдельный ночной джоб, который смотрит на весь диалог целиком
 * и понимает исход (купил/отказался/замолчал), а не на одно сообщение. Сама
 * задача этого джоба на 2026-09-13 ещё не реализована — таблица создана заранее,
 * под него.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_lead_id')->constrained('whatsapp_leads')->cascadeOnDelete();
            $table->foreignId('lead_request_id')->nullable()->constrained('lead_requests')->nullOnDelete();

            $table->string('source', 50)->nullable()->index(); // site / 2gis — денормализовано с лида
            $table->string('phone', 30)->nullable()->index(); // денормализовано с лида, чтобы не джойнить лишний раз

            $table->string('vin', 20)->nullable();
            $table->string('brand', 100)->nullable()->index();
            $table->string('car_model', 150)->nullable()->index();
            $table->string('car_year', 10)->nullable();

            $table->string('part_name', 255)->index(); // "опора амортизатора" и т.п.
            $table->string('part_side', 20)->nullable(); // left/right/null
            $table->string('part_position', 20)->nullable(); // front/rear/upper/lower/null

            // Наш ответ клиенту по этой позиции
            $table->enum('availability_answer', ['in_stock', 'on_order', 'not_found', 'unknown'])->default('unknown')->index();
            $table->unsignedSmallInteger('lead_time_days')->nullable(); // срок, если "под заказ"
            $table->decimal('quoted_price', 12, 2)->nullable();

            // Чем закончилось
            $table->enum('outcome', ['bought', 'declined', 'silent', 'pending'])->default('pending')->index();
            $table->decimal('outcome_amount', 12, 2)->nullable(); // фактическая сумма, если купил

            $table->text('notes')->nullable(); // произвольный комментарий модели/причина отказа своими словами
            $table->json('raw_llm_response')->nullable(); // полный ответ ночного анализа по этой позиции, для отладки

            $table->timestamp('analyzed_at')->nullable(); // когда ночной джоб посчитал эту строку
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_signals');
    }
};
