<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Уровень группы (5-й элемент characteristics->category_path, например
     * "Ignition system"/"Система зажигания, накаливания") — подкатегория внутри
     * верхнеуровневой (category_top_code). Не у всех карточек есть этот уровень
     * (напр. "Автомобильные фильтры" — сразу лист без группы), поэтому nullable.
     */
    public function up(): void
    {
        Schema::table('parts_catalog', function (Blueprint $table) {
            $table->string('category_group_code')->nullable()->after('category_slug');
            $table->string('category_group_title')->nullable()->after('category_group_code');
            $table->string('category_group_slug')->nullable()->after('category_group_title');

            $table->index(['category_top_code', 'category_group_slug'], 'idx_category_top_group');
        });

        DB::statement(<<<'SQL'
            UPDATE parts_catalog
            SET
                category_group_code = JSON_UNQUOTE(JSON_EXTRACT(characteristics, '$.category_path[4].code')),
                category_group_title = JSON_UNQUOTE(JSON_EXTRACT(characteristics, '$.category_path[4].title')),
                category_group_slug = LOWER(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(characteristics, '$.category_path[4].code')), ' ', '-'))
            WHERE characteristics IS NOT NULL
              AND JSON_LENGTH(JSON_EXTRACT(characteristics, '$.category_path')) >= 5
        SQL);
    }

    public function down(): void
    {
        Schema::table('parts_catalog', function (Blueprint $table) {
            $table->dropIndex('idx_category_top_group');
            $table->dropColumn(['category_group_code', 'category_group_title', 'category_group_slug']);
        });
    }
};
