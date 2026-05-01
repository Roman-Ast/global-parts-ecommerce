<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('whatsapp_lead_id')->constrained()->onDelete('cascade');
            $blueprint->string('instance_id', 50)->index();
            $blueprint->string('message_id', 255)->unique(); // ID от Green API
            $blueprint->text('message_text')->nullable();
            $blueprint->string('file_url', 500)->nullable();
            $blueprint->string('type', 50)->default('chat'); // image, chat, audio
            $blueprint->boolean('is_incoming')->default(true);
            $blueprint->json('raw_body')->nullable(); // Сохраняем весь ответ от API на всякий случай
            $blueprint->string('status', 20)->default('delivered');
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
