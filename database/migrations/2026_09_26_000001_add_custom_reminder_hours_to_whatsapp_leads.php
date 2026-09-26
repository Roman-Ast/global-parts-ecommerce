<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Просьба Романа 2026-09-26 — "Долгоиграющие" со ВСЕМИ одним 10-дневным
 * порогом не годится: у клиента "на больничном, на след. неделе напишем"
 * 11 дней многовато, а у "ждём страховку" (2 недели-месяц) — маловато.
 * Индивидуальный интервал на лида, см. App\Support\LeadStatuses::
 * REMINDER_INTERVAL_PRESETS/needsReminder(). NULL = используется дефолт
 * по статусу (10 дней для 'long_term').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_leads', function (Blueprint $table) {
            $table->unsignedSmallInteger('custom_reminder_hours')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_leads', function (Blueprint $table) {
            $table->dropColumn('custom_reminder_hours');
        });
    }
};
