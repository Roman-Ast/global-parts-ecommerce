<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Живая ошибка на проде 2026-09-18 при оформлении заказа (PDOException
 * "Unknown column 'account_id' in 'order_payments'"): таблица
 * `order_payments` на проде существует, но без `account_id`. Причина —
 * создающая миграция (`2026_02_28_000007_create_order_payments_table`)
 * оборачивает `Schema::create` в `if (!Schema::hasTable(...))` — если
 * таблица к моменту миграции уже существовала (создана раньше каким-то
 * другим путём, тот же класс дрейфа схемы, что уже не раз всплывал в ERP-
 * разделе CLAUDE.md), сама колонка из определения миграции так и не
 * применилась, хотя миграция отметилась как выполненная.
 *
 * Добавляем только то, чего реально не хватает (hasColumn-гварды на
 * каждую) — безопасно для окружений, где всё уже в порядке (там просто
 * ничего не сделает).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('order_payments', 'account_id')) {
                $table->unsignedBigInteger('account_id')->after('order_id');
                $table->index(['account_id', 'paid_at']);
                $table->foreign('account_id')
                    ->references('id')
                    ->on('accounts')
                    ->onDelete('restrict')
                    ->onUpdate('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            if (Schema::hasColumn('order_payments', 'account_id')) {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            }
        });
    }
};
