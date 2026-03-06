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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->nullable()->unique(); // legacy-код: atpr/rssk/shtm и т.д.
            $table->string('name', 150);                      // человекочитаемое имя поставщика
            $table->integer('lead_days')->nullable();         // срок поставки (дней)
            $table->integer('grace_days')->default(0);        // отсрочка после срока поставки (дней), напр. +5
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
        Schema::dropIfExists('suppliers');
    }
};

/*
Что делает миграция:
- Создает справочник suppliers (поставщики).
- Поля lead_days и grace_days нужны для расчета плановой даты оплаты (кредиторки):
  due_date = settlement_date + lead_days + grace_days
- code хранит твой legacy-код поставщика (atpr/rssk/...), чтобы можно было замаппить старые записи.
*/
