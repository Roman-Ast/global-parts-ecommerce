<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Сырой результат разбора КОНКРЕТНОГО сообщения (текста или вложения — фото/PDF
 * техпаспорта) слоем LLM (App\Services\ClaudeExtractionService), ДО того как он
 * смёрджен в lead_requests. Нужно, чтобы видеть, что именно распознала модель
 * по каждому сообщению отдельно (отладка/аудит), а не только итоговый
 * смёрдженный результат по лиду.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('extracted_vin', 20)->nullable()->after('raw_body');
            $table->string('extracted_brand', 100)->nullable()->after('extracted_vin');
            $table->string('extracted_car_model', 150)->nullable()->after('extracted_brand');
            $table->string('extracted_car_year', 10)->nullable()->after('extracted_car_model');
            $table->json('extracted_parts_json')->nullable()->after('extracted_car_year');
            $table->json('llm_raw_response')->nullable()->after('extracted_parts_json'); // полный ответ модели, как есть
            $table->timestamp('llm_processed_at')->nullable()->after('llm_raw_response');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn([
                'extracted_vin', 'extracted_brand', 'extracted_car_model',
                'extracted_car_year', 'extracted_parts_json', 'llm_raw_response', 'llm_processed_at',
            ]);
        });
    }
};
