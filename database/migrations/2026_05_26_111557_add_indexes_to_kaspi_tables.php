<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kaspi_initial_products', function (Blueprint $table) {
            // составной — главный для матчинга
            $table->index(['sku', 'brand'], 'idx_kip_sku_brand');
            $table->index('supplier_name', 'idx_kip_supplier');
            $table->index('kaspi_parsed', 'idx_kip_parsed');
            $table->index('stock', 'idx_kip_stock');
        });

        Schema::table('kaspi_sku_test', function (Blueprint $table) {
            $table->index('request_article', 'idx_kst_article');
            $table->index('sku', 'idx_kst_sku');
        });
    }

    public function down(): void
    {
        Schema::table('kaspi_initial_products', function (Blueprint $table) {
            $table->dropIndex('idx_kip_sku_brand');
            $table->dropIndex('idx_kip_supplier');
            $table->dropIndex('idx_kip_parsed');
            $table->dropIndex('idx_kip_stock');
        });

        Schema::table('kaspi_sku_test', function (Blueprint $table) {
            $table->dropIndex('idx_kst_article');
            $table->dropIndex('idx_kst_sku');
        });
    }
};
