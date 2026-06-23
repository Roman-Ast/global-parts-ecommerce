<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaspi_product_matches', function (Blueprint $table) {
            $table->id();

            // твой товар
            $table->string('article');        // = kaspi_initial_products.sku
            $table->string('brand');          // = kaspi_initial_products.brand
            $table->string('supplier_name');  // shatem / другие в будущем

            // каспи карточка
            $table->string('kaspi_sku');      // = kaspi_sku_test.sku (ID карточки каспи)
            $table->string('kaspi_name');     // название с каспи — идёт в фид

            // управление
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // один товар не может дважды матчиться к одному каспи SKU
            $table->unique(['article', 'supplier_name', 'kaspi_sku'], 'uq_match');

            $table->index('article', 'idx_kpm_article');
            $table->index('kaspi_sku', 'idx_kpm_kaspi_sku');
            $table->index('is_active', 'idx_kpm_active');
            $table->index('supplier_name', 'idx_kpm_supplier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaspi_product_matches');
    }
};