<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `decline_reason` ENUM отставал от реальной воронки CRM — 'no_response'
 * ("Не отвечает") добавлен в LeadStatuses 2026-09-22 как отдельная
 * lost-подпричина, но сюда так и не попал. Живьём поймано 2026-10-05 при
 * ручном разборе батча заявок: INSERT падал с "Data truncated for column
 * 'decline_reason'" на лиде со статусом no_response.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE demand_signals MODIFY decline_reason ENUM('no_stock_wont_wait','in_stock_too_expensive','part_not_found','changed_mind','no_response') COLLATE utf8mb4_unicode_ci DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE demand_signals MODIFY decline_reason ENUM('no_stock_wont_wait','in_stock_too_expensive','part_not_found','changed_mind') COLLATE utf8mb4_unicode_ci DEFAULT NULL");
    }
};
