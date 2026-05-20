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
        Schema::create('kaspi_initial_products', function (Blueprint $table) {
            $table->id();
            
            // Уникальный идентификатор товара (чистый артикул или артикул+бренд)
            $table->string('sku'); 
            $table->string('title');
            $table->string('brand');
            
            // Сюда будем писать код категории Kaspi, полученный по API
            $table->string('category_code')->nullable(); 
            $table->text('description')->nullable();
            
            // Текущие цена и остаток, чтобы сверять дельту
            $table->decimal('price', 12, 2)->default(0.00);
            $table->integer('stock')->default(0);
            
            // JSON-колонки для картинок и динамических характеристик (атрибутов)
            $table->json('images')->nullable(); // Сюда пишем ['https://url1', 'https://url2']
            $table->json('attributes')->nullable(); // Сюда пишем [['code' => '...', 'value' => '...']]
            
            // Дополнительное поле для хранения сырых кросс-номеров от поставщика (пригодится)
            $table->text('raw_cross_numbers')->nullable();
            $table->unique(['sku', 'brand']);

            $table->timestamps();
            
            // Индексы для быстрой выборки по бренду
            $table->index(['brand', 'sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kaspi_initial_products');
    }
};
