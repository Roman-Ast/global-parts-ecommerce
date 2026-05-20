<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaspi_update_queue', function (Blueprint $table) {
            $table->id();
            
            $table->string('sku')->index(); // Артикул для Kaspi
            $table->decimal('price', 12, 2);
            $table->integer('stock');
            
            // Статус отправки (0 - ждет отправки, 1 - успешно отправлено, 2 - ошибка)
            $table->tinyInteger('status')->default(0)->index(); 
            $table->text('error_message')->nullable(); // Сюда запишем ошибку от Kaspi API, если она будет

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaspi_update_queue');
    }
};
