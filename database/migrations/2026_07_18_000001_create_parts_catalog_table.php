<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parts_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('article');
            $table->string('article_normalized'); // очищено от спецсимволов, uppercase — для дедупликации
            $table->string('brand');
            $table->string('brand_normalized');
            $table->string('name', 500)->nullable();
            $table->text('description')->nullable();
            $table->json('characteristics')->nullable(); // всё, что найдём в specifications карточки
            $table->text('applicability')->nullable(); // применимость (марки/модели), если есть на карточке
            $table->json('images')->nullable(); // массив URL картинок
            $table->string('source', 20); // 'own' | 'competitor'
            $table->string('source_kaspi_sku')->nullable();
            $table->string('source_merchant_id')->nullable();
            $table->string('scrape_status', 20)->default('pending'); // pending/done/failed/not_found
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->unique(['article_normalized', 'brand_normalized'], 'uniq_article_brand');
            $table->index('article_normalized', 'idx_article_normalized');
            $table->index('brand_normalized', 'idx_brand_normalized');
            $table->index('source', 'idx_source');
            $table->index('scrape_status', 'idx_scrape_status');
            $table->index('source_kaspi_sku', 'idx_source_kaspi_sku');
            $table->index('source_merchant_id', 'idx_source_merchant_id');
        });

        // FULLTEXT индекс — Laravel Blueprint не имеет нативного метода для InnoDB FULLTEXT
        // на всех версиях одинаково надёжно, добавляем сырым SQL
        DB::statement('ALTER TABLE parts_catalog ADD FULLTEXT ft_name (name)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parts_catalog');
    }
};