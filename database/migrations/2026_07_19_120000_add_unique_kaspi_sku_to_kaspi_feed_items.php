<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * До этой миграции kaspi_sku имел обычный (не уникальный) индекс,
     * из-за чего upsert(['kaspi_sku'], ...) в MatchKaspiSkuCommand
     * не находил конфликтов и просто вставлял новую строку при каждом
     * прогоне на разных поставщиках — вместо обновления существующей.
     * Итог: один и тот же kaspi_sku накапливался дублями (по одному на
     * supplier_name), что ломало дедупликацию в других местах (например,
     * при посеве parts_catalog).
     *
     * ВАЖНО: перед применением этой миграции нужно вручную удалить
     * существующие дубли (см. чат/доку), иначе миграция упадёт с
     * ошибкой "Duplicate entry" при попытке создать unique index.
     */
    public function up(): void
    {
        Schema::table('kaspi_feed_items', function (Blueprint $table) {
            $table->unique('kaspi_sku', 'uniq_kaspi_feed_items_kaspi_sku');
        });
    }

    public function down(): void
    {
        Schema::table('kaspi_feed_items', function (Blueprint $table) {
            $table->dropUnique('uniq_kaspi_feed_items_kaspi_sku');
        });
    }
};