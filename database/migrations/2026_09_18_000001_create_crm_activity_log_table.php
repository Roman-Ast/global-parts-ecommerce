<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Простой лог активности в СРМ (просьба Романа 2026-09-18) — пока для
 * 2 пользователей (он + коллега), отслеживать кто когда начал/закончил
 * работу и сколько карточек обработал. Никакой вьюхи под это пока не
 * строим — смотреть через прямой SQL, "начало/конец дня" и т.п. считаются
 * агрегацией (MIN/MAX created_at по user_id+дате) поверх сырых событий,
 * не отдельными полями "начал"/"закончил" — простая append-only таблица
 * событий гибче, чем пытаться угадывать заранее, что считать "сессией".
 *
 * Пример агрегации по дням:
 *   SELECT user_id, DATE(created_at) AS day,
 *          MIN(created_at) AS started_at, MAX(created_at) AS ended_at,
 *          COUNT(*) AS total_actions,
 *          SUM(action = 'update_status') AS cards_moved,
 *          COUNT(DISTINCT CASE WHEN action = 'update_status' THEN whatsapp_lead_id END) AS unique_leads_touched
 *   FROM crm_activity_log
 *   GROUP BY user_id, DATE(created_at)
 *   ORDER BY day DESC, user_id;
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // 'view_board' (открыл доску) / 'open_chat' / 'update_status' / 'send_message'
            $table->string('action', 40);
            $table->foreignId('whatsapp_lead_id')->nullable()->constrained()->nullOnDelete();
            // update_status: {"from":"new","to":"payment"}; остальные — пока не используется
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activity_log');
    }
};
