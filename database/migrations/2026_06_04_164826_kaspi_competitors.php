<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaspi_competitors', function (Blueprint $table) {
            $table->id();
            $table->string('kaspi_sku');
            $table->string('request_article');
            $table->string('merchant_id');
            $table->string('merchant_name')->nullable();
            $table->decimal('merchant_rating', 3, 1)->nullable();
            $table->unsignedInteger('merchant_reviews')->default(0);
            $table->decimal('price', 12, 2)->nullable();
            $table->string('delivery_duration')->nullable(); // TOMORROW, TILL_2_DAYS, OTHER
            $table->unsignedInteger('preorder_days')->default(0);
            $table->timestamp('parsed_at')->nullable();

            $table->index('kaspi_sku');
            $table->index('request_article');
            $table->index('merchant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaspi_competitors');
    }
};
