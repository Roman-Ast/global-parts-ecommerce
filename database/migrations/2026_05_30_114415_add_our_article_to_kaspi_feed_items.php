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
        Schema::table('kaspi_feed_items', function (Blueprint $table) {
            $table->dropUnique('uq_kaspi_sku_supplier');
            $table->unique(['kaspi_sku', 'supplier_name', 'our_article'], 'uq_kaspi_sku_supplier_article');
        });
    }

    public function down(): void
    {
        Schema::table('kaspi_feed_items', function (Blueprint $table) {
            $table->dropUnique('uq_kaspi_sku_supplier_article');
            $table->dropColumn('our_article');
            $table->unique(['kaspi_sku', 'supplier_name'], 'uq_kaspi_sku_supplier');
        });
    }
};
