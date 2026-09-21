<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Оптимизация под CRM-канбан (просьба Романа 2026-09-21 — доска
 * подтормаживает, "если есть узкие места — давай пригасим/урежем").
 *
 * KanbanBoard::render() и WhatsappMessenger::render() дёргаются каждые 3
 * секунды (wire:poll.3s) и на каждый тик гоняют:
 *   - WhatsappLead::where('status', '!=', ...)->orderByDesc('updated_at')
 *     — индекса под сортировку не было вообще (только отдельный индекс на
 *     status и отдельный на last_seen_at, а сортировка идёт по updated_at).
 *     ВАЖНО: составной индекс (status, updated_at) здесь НЕ помогает —
 *     проверено живьём через EXPLAIN, MySQL не использует его из-за `!=`
 *     (неравенство на первой колонке не sargable для составных индексов).
 *     Раз spam — маленькая доля лидов, а не большинство, обычный индекс
 *     ПРОСТО по updated_at даёт MySQL идти по нему в нужном порядке и
 *     пропускать редкие spam-строки на лету, без filesort — это и есть
 *     рабочий паттерн под "ORDER BY X LIMIT N с редким исключающим
 *     фильтром".
 *   - withCount(['messages as unread_count' => where is_incoming/is_read])
 *     на каждого из до 200 лидов разом — на whatsapp_messages был только
 *     голый FK-индекс на whatsapp_lead_id, без is_incoming/is_read.
 *   - lastMessage() (latestOfMany, по сути ORDER BY created_at DESC LIMIT 1
 *     на лида) — тот же голый FK-индекс, без created_at.
 *
 * На 137 лидах/1440 сообщениях локально разницы не видно, но на проде
 * (реальный трафик СРМ, запрос уходит по сети на шаред-хостинг) это и
 * есть узкое место, которое проще всего снять индексами — без изменения
 * кода/логики, чистый выигрыш. Все шаги идемпотентны (проверяют
 * существование индекса перед добавлением) — безопасно перезапускать.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->indexExists('whatsapp_leads', 'whatsapp_leads_updated_at_index')) {
            Schema::table('whatsapp_leads', function (Blueprint $table) {
                $table->index('updated_at', 'whatsapp_leads_updated_at_index');
            });
        }

        // whatsapp_lead_id уже покрыт ведущей колонкой каким-то индексом
        // на этой таблице (родной FK-индекс либо составной, если он уже
        // существует) — InnoDB требует хотя бы один такой индекс, поэтому
        // просто проверяем каждый нужный составной отдельно, не трогая
        // остальные.
        if (!$this->indexExists('whatsapp_messages', 'whatsapp_messages_lead_incoming_read_index')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->index(['whatsapp_lead_id', 'is_incoming', 'is_read'], 'whatsapp_messages_lead_incoming_read_index');
            });
        }

        if (!$this->indexExists('whatsapp_messages', 'whatsapp_messages_lead_created_at_index')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->index(['whatsapp_lead_id', 'created_at'], 'whatsapp_messages_lead_created_at_index');
            });
        }
    }

    public function down(): void
    {
        // Осознанно пусто — чисто добавочная оптимизация индексов, откат
        // не нужен (а сам откат составных FK-покрывающих индексов на
        // MySQL/InnoDB, как выяснилось живьём, не всегда предсказуем).
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $dbName = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $dbName)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
