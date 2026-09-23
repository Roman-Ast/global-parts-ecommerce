<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Материализованная замена "живому" GROUP BY на главной странице
 * (HomeController::index() → popularCategories). Просьба Романа
 * 2026-09-23 после разбора причины 9-секундной загрузки главной —
 * GROUP BY по 91к+ строкам parts_catalog на каждый визит (2.2 сек
 * локально на 50к) сначала завели под часовой Cache::remember(), но
 * Роман явно попросил пойти дальше: "посчитаем один раз и уберём этот
 * процесс вообще, даже без кеша" — таблица parts_catalog обновляется
 * редко (ручные прогоны скрейпинга), пересчитывать на лету вообще не
 * нужно. HomeController теперь просто читает эту маленькую таблицу
 * (десятки строк) — мгновенно в любой момент, без единого "невезучего"
 * посетителя, который попадает на холодный кэш. Обновляется командой
 * `catalog:refresh-popular-categories`, вручную Романом после прогона
 * скрейпинга (см. докблок команды).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popular_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_slug');
            $table->string('category_top_title')->nullable();
            $table->string('category_top_code')->nullable();
            $table->unsignedInteger('cnt');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popular_categories');
    }
};
