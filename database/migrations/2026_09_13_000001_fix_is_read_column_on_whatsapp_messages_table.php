<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Фактически добавляет `is_read`, которую старая миграция
 * (2026_04_15_184050_add_is_read_to_whatsapp_messages_table) должна была
 * добавить, но из-за инвертированного условия молча этого не сделала —
 * та миграция уже отмечена выполненной в `migrations`, поэтому Laravel её
 * не перезапустит, даже после фикса самого условия.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_messages') && !Schema::hasColumn('whatsapp_messages', 'is_read')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->boolean('is_read')->default(false)->after('message_text');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('whatsapp_messages', 'is_read')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                $table->dropColumn('is_read');
            });
        }
    }
};
