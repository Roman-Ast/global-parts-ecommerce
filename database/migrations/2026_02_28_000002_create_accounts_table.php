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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);                 // Kaspi Gold Roman / Kaspi Pay / Halyk Roman / Cash ...
            $table->char('currency', 3)->default('KZT'); // валюта
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};

/*
Что делает миграция:
- Создает accounts (твои счета/кошельки), чтобы любой приход/расход можно было привязать к конкретному счету.
- Дальше это используется в ДДС и в оплатах (клиенты/поставщики).
*/
