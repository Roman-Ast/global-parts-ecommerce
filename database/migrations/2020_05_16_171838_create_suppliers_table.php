<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            
            $table->string('name'); // Название: Фаэтон, ЗаказАвто и т.д.
            $table->string('email')->nullable()->unique(); // email, с которого летят прайсы
            $table->string('phone')->nullable();
            $table->string('contact_person')->nullable(); // Контактное лицо менеджера
            
            // Дополнительные поля для CRM (активен ли поставщик, условия работы)
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
