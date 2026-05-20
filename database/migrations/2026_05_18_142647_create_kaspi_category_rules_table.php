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
        Schema::create('kaspi_category_rules', function (Blueprint $table) {
            $table->id();
            $table->string('keyword')->unique(); // Ключевое слово (например: "ремень", "рычаг")
            $table->string('kaspi_code');        // Код Каспи (например: "Master - Vehicle drive belts")
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kaspi_category_rules');
    }
};
