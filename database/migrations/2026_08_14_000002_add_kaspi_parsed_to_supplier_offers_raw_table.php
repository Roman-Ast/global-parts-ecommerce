<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Тот же флаг, что и kaspi_initial_products.kaspi_parsed — 0 значит
     * "ещё ни разу не искали на Kaspi", 1 — "уже искали (неважно, нашли или
     * нет), повторно kaspi:parse-sku-raw не трогает". Бэкфилл ниже копирует
     * статус из kaspi_initial_products по паре (sku, brand) — то, что уже
     * когда-то искалось через боевой kaspi:parse-sku, повторно искать не
     * нужно. Новые строки (которых в kaspi_initial_products никогда не было
     * — в основном из-за фильтра stock>=2/цена>=5000) остаются 0.
     */
    public function up(): void
    {
        Schema::table('supplier_offers_raw', function (Blueprint $table) {
            $table->boolean('kaspi_parsed')->default(false)->after('preorder_days');
            $table->index('kaspi_parsed', 'idx_kaspi_parsed');
        });

        DB::statement("
            UPDATE supplier_offers_raw sor
            JOIN kaspi_initial_products kip
              ON kip.sku = sor.sku
             AND UPPER(TRIM(kip.brand)) = UPPER(TRIM(sor.brand))
            SET sor.kaspi_parsed = kip.kaspi_parsed
        ");
    }

    public function down(): void
    {
        Schema::table('supplier_offers_raw', function (Blueprint $table) {
            $table->dropIndex('idx_kaspi_parsed');
            $table->dropColumn('kaspi_parsed');
        });
    }
};
