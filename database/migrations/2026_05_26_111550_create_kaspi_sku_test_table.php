<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaspi_sku_test', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('request_article')->nullable();
            $table->string('sku');
            $table->string('name');
            $table->integer('competitors_min_price')->nullable();
            $table->tinyInteger('competitors_tomorrow_count')->default(0);
            $table->tinyInteger('competitors_total')->default(0);
            $table->integer('kaspi_qty')->nullable();
            $table->tinyInteger('qty_suspicious')->default(0);
            $table->timestamp('competitors_parsed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaspi_sku_test');
    }
};