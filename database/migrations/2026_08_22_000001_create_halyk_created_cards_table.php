<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Отслеживание пилота halyk:create-card (см. CLAUDE.md, раздел "Halyk
     * Market") — по одной строке на попытку создания карточки. status
     * проходит submitted → (moderation|reject|deleted|success) после
     * halyk:check-card-status, или skipped сразу, если чего-то не хватило
     * (нет обязательной характеристики, не нашлась категория/бренд и т.д.).
     */
    public function up(): void
    {
        Schema::create('halyk_created_cards', function (Blueprint $table) {
            $table->id();
            $table->string('article');
            $table->string('brand');
            $table->unsignedBigInteger('parts_catalog_id')->nullable();
            $table->unsignedBigInteger('halyk_category_id')->nullable();
            $table->unsignedBigInteger('halyk_brand_id')->nullable();
            $table->unsignedBigInteger('halyk_product_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('skip_reason')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index('article');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('halyk_created_cards');
    }
};
