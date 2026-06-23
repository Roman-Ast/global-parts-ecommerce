<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaspi_feed_items', function (Blueprint $table) {
            $table->id();

            // данные каспи (из матча)
            $table->string('kaspi_sku')->unique(); // один SKU = одна строка в фиде
            $table->string('kaspi_name');

            // твои актуальные данные (обновляются ежедневно)
            $table->decimal('price', 12, 2);
            $table->integer('stock')->default(0);
            $table->integer('preorder_days')->default(0);

            // мета
            $table->string('supplier_name');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable(); // когда последний раз обновлялись цена/остаток
            $table->timestamps();

            $table->index('is_active', 'idx_kfi_active');
            $table->index('supplier_name', 'idx_kfi_supplier');
            $table->index('last_synced_at', 'idx_kfi_synced');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaspi_feed_items');
    }
};