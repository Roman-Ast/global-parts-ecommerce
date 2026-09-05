<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Отслеживание ozon:create-card (см. CLAUDE.md, раздел "Ozon") — по
     * одной строке на попытку создания карточки, тот же принцип, что и
     * halyk_created_cards. status: submitted (task_id получен, статус
     * ещё не проверен) → imported/failed (после опроса
     * /v1/product/import/info), либо skipped сразу, если не хватило
     * категории/бренда.
     */
    public function up(): void
    {
        Schema::create('ozon_created_cards', function (Blueprint $table) {
            $table->id();
            $table->string('article');
            $table->string('brand');
            $table->unsignedBigInteger('parts_catalog_id')->nullable();
            $table->unsignedBigInteger('ozon_category_id')->nullable();
            $table->unsignedBigInteger('ozon_type_id')->nullable();
            $table->unsignedBigInteger('ozon_task_id')->nullable();
            $table->unsignedBigInteger('ozon_product_id')->nullable();
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
        Schema::dropIfExists('ozon_created_cards');
    }
};
