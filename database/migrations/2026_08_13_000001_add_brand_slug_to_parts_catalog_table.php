<?php

use App\Helpers\SlugHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * brand_slug — человекочитаемый слаг бренда для URL /product/{brand}/{article}
     * (и для обратного поиска по нему), в отличие от brand_normalized (аплкейс
     * с пробелами, служит только для JOIN с supplier_offers). Не generated-
     * колонка, как article_normalized/brand_normalized — транслитерация
     * кириллицы (SlugHelper::CYRILLIC_MAP) не выражается компактно чистым SQL,
     * поэтому колонка обычная, заполняется в PHP (тут бэкфиллом и дальше в
     * SeedOwnPartsCatalogCommand/ScrapeKaspiCardsCommand при записи).
     *
     * Не unique: разные исходные написания бренда теоретически могут
     * схлопнуться в один и тот же слаг (пробел/дефис, лишний регистр) —
     * это допустимо, при коллизии /product/{brand}/{article} просто берёт
     * первую подходящую по (brand_slug, article_normalized) карточку.
     */
    public function up(): void
    {
        Schema::table('parts_catalog', function (Blueprint $table) {
            $table->string('brand_slug')->nullable()->after('brand_normalized');
            $table->index(['brand_slug', 'article_normalized'], 'idx_brand_slug_article_normalized');
        });

        DB::table('parts_catalog')
            ->select('id', 'brand')
            ->orderBy('id')
            ->chunkById(2000, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('parts_catalog')
                        ->where('id', $row->id)
                        ->update(['brand_slug' => SlugHelper::brandToSlug($row->brand)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('parts_catalog', function (Blueprint $table) {
            $table->dropIndex('idx_brand_slug_article_normalized');
            $table->dropColumn('brand_slug');
        });
    }
};
