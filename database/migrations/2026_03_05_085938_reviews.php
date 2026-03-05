<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            $table->string('src')->nullable(); // источник отзыва (2gis, google, site)

            $table->string('author'); // имя автора
            $table->date('date'); // дата отзыва
            $table->string('avatar')->nullable(); // путь к аватару

            $table->text('text'); // текст отзыва
            $table->tinyInteger('rate'); // рейтинг 1-5

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
