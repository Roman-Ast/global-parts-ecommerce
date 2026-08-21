<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Пилотная таблица под поиск/сопоставление/привязку на Halyk Market —
     * аналог kaspi_sku_test, но без отдельной "сырой" и "боевой" таблицы,
     * пока это just proof-of-concept на 100 позициях (см. CLAUDE.md,
     * раздел "Halyk Market"). Один request_article может иметь несколько
     * строк-кандидатов (halyk:search-sku пишет топ-N результатов поиска),
     * halyk:match помечает лучший matched=true, halyk:bind — bound=true.
     */
    public function up(): void
    {
        Schema::create('halyk_sku_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('request_article');
            $table->string('request_brand')->nullable();
            $table->unsignedBigInteger('halyk_sku_id')->nullable();
            $table->string('halyk_name')->nullable();
            $table->unsignedBigInteger('halyk_category_id')->nullable();
            $table->string('halyk_category_name')->nullable();
            $table->string('halyk_market_url')->nullable();
            $table->boolean('matched')->default(false);
            $table->boolean('bound')->default(false);
            $table->timestamp('bound_at')->nullable();
            $table->timestamp('searched_at')->nullable();
            $table->timestamps();

            $table->index('request_article');
            $table->index('matched');
            $table->index('bound');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('halyk_sku_candidates');
    }
};
