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
        Schema::create('kaspi_unmapped', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('raw_name');
            $table->string('sku');
            $table->timestamps();
            $table->unique(['brand', 'sku']); // Защита от дублей
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kaspi_unmapped');
    }
};
