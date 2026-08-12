<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Generated-колонки (не обычный backfill) — так они гарантированно
     * остаются в синхроне при любых будущих INSERT/UPDATE от
     * AggregateSupplierOffersCommand без изменения самой команды.
     * Нормализация продублирована из
     * SeedOwnPartsCatalogCommand::normalizeArticle()/normalizeBrand(), чтобы
     * JOIN с parts_catalog.article_normalized/brand_normalized совпадал 1:1.
     * Нужно для быстрого поиска цены поставщика под карточку каталога
     * (см. CatalogController) — без индекса JOIN на 121k+ строк с
     * REGEXP_REPLACE "на лету" занимал ~300ms на страницу.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE supplier_offers
            ADD COLUMN sku_normalized VARCHAR(255)
                GENERATED ALWAYS AS (UPPER(REGEXP_REPLACE(sku, '[^a-zA-Zа-яА-Я0-9]', ''))) STORED,
            ADD COLUMN brand_normalized VARCHAR(255)
                GENERATED ALWAYS AS (UPPER(TRIM(brand))) STORED
        ");

        DB::statement('ALTER TABLE supplier_offers ADD INDEX idx_sku_brand_normalized (sku_normalized, brand_normalized)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE supplier_offers DROP INDEX idx_sku_brand_normalized');
        DB::statement('ALTER TABLE supplier_offers DROP COLUMN sku_normalized, DROP COLUMN brand_normalized');
    }
};
