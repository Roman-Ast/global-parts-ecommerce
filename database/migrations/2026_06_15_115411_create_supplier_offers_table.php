<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_offers', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->string('supplier_name');
            $table->string('title');
            $table->string('brand');
            $table->decimal('purchase_price', 12, 2);
            $table->integer('stock');
            $table->timestamps();

            $table->unique(['sku', 'supplier_name']);
            $table->index(['sku', 'purchase_price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_offers');
    }
};