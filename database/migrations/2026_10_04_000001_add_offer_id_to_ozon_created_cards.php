<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * offer_id был всегда НЕЯВНЫМ — вычислялся на лету как substr("{brand}-{article}",
 * 0, 50) везде, где нужен (OzonCreateCardCommand::resolveOfferId,
 * OzonPushStockCommand, OzonFixToRosskoShatemCommand). Это ломается ровно в
 * том сценарии, который понадобился 2026-10-04 — замена контента мёртвого
 * слота (старый article/brand) на новую позицию (другой article/brand) под
 * ТЕМ ЖЕ самым offer_id, что уже занят на Ozon: вычисление от НОВОГО
 * article/brand даёт ДРУГОЙ offer_id, не совпадающий с реальным. Делаем
 * offer_id явной колонкой — источник истины, не производная.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ozon_created_cards', function (Blueprint $table) {
            $table->string('offer_id', 50)->nullable()->after('brand');
        });

        // Бэкфилл — та же формула, что везде использовалась неявно.
        DB::statement("UPDATE ozon_created_cards SET offer_id = LEFT(CONCAT(brand, '-', article), 50) WHERE offer_id IS NULL");

        Schema::table('ozon_created_cards', function (Blueprint $table) {
            $table->index('offer_id');
        });
    }

    public function down(): void
    {
        Schema::table('ozon_created_cards', function (Blueprint $table) {
            $table->dropColumn('offer_id');
        });
    }
};
