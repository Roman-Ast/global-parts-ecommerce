<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_leads', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('phone', 191)->unique();
            $blueprint->string('client_name', 255)->nullable();
            $blueprint->string('last_vin', 17)->nullable()->index();
            
            // Наша воронка (по умолчанию ставим 'new')
            $blueprint->string('status', 50)->default('new')->index();
            
            // Новые поля для Канбана и дожима
            $blueprint->string('delivery_source', 50)->nullable(); // local, russia, uae
            $blueprint->decimal('deal_sum', 12, 2)->default(0);    // Сумма сделки
            $blueprint->text('order_details')->nullable();        // Что именно купили
            $blueprint->string('objection_type', 50)->nullable();  // дорого, долго, молчит
            $blueprint->timestamp('next_action_at')->nullable();   // Когда напомнить
            
            $blueprint->string('source', 50)->nullable()->index(); // site, 2gis и т.д.
            $blueprint->timestamp('last_seen_at')->nullable()->index();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_leads');
    }
};
