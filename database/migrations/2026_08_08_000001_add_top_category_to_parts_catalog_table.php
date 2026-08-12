<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Верхнеуровневая категория (4-й элемент characteristics->category_path,
     * например "Engine"/"Двигатель") вынесена в плоские колонки — плитки
     * каталога на главной и страница категории не должны на каждый запрос
     * гонять JSON_EXTRACT по 50k+ строк.
     */
    public function up(): void
    {
        Schema::table('parts_catalog', function (Blueprint $table) {
            $table->string('category_top_code')->nullable()->after('applicability');
            $table->string('category_top_title')->nullable()->after('category_top_code');
            $table->string('category_slug')->nullable()->after('category_top_title');

            $table->index('category_top_code', 'idx_category_top_code');
            $table->index('category_slug', 'idx_category_slug');
        });

        // Бэкфилл из уже собранных карточек. Коды категорий (path[3].code) —
        // латиница без спецсимволов ("Car chassis", "Fuel supply system"),
        // поэтому слаг собирается прямо в SQL без обращения к Str::slug.
        DB::statement(<<<'SQL'
            UPDATE parts_catalog
            SET
                category_top_code = JSON_UNQUOTE(JSON_EXTRACT(characteristics, '$.category_path[3].code')),
                category_top_title = JSON_UNQUOTE(JSON_EXTRACT(characteristics, '$.category_path[3].title')),
                category_slug = LOWER(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(characteristics, '$.category_path[3].code')), ' ', '-'))
            WHERE characteristics IS NOT NULL
              AND JSON_LENGTH(JSON_EXTRACT(characteristics, '$.category_path')) >= 4
        SQL);
    }

    public function down(): void
    {
        Schema::table('parts_catalog', function (Blueprint $table) {
            $table->dropIndex('idx_category_top_code');
            $table->dropIndex('idx_category_slug');
            $table->dropColumn(['category_top_code', 'category_top_title', 'category_slug']);
        });
    }
};
