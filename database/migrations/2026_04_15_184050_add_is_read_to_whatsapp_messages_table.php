<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Было: `if (!Schema::hasTable(...))` — инвертированное условие, из-за которого
        // ALTER выполнялся только когда таблицы НЕТ (то есть фактически никогда), и
        // колонка молча не добавлялась. См. 2026_09_13_000001_fix_is_read_column
        // за фактическим фиксом на уже смигрированных окружениях.
        if (Schema::hasTable('whatsapp_messages') && !Schema::hasColumn('whatsapp_messages', 'is_read')) {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                // Ставим по умолчанию true для наших сообщений и false для входящих
                $table->boolean('is_read')->default(false)->after('message_text');
            });
        }
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn('is_read');
        });
    }
};
