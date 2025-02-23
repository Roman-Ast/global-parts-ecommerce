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
        Schema::create('office_price', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('oem');
            $table->string('article');
            $table->string('brand');
            $table->string('name');
            $table->double('price');
            $table->double('qty');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('office_price');
    }
};
