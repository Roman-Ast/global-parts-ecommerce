<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Продолжение фикса из 2026_09_18_000002 — та миграция упала на попытке
 * создать индекс (account_id, paid_at), потому что `paid_at` на проде тоже
 * не существовало (не только account_id) — реальная структура прода
 * оказалась совсем другой: есть `cashflow_transaction_id`/`method`,
 * которых нет ни в одной локальной миграции (получено живым
 * `SHOW COLUMNS FROM order_payments` от Романа 2026-09-18). ALTER TABLE в
 * MySQL не транзакционный — `account_id` из той миграции уже реально
 * добавился на проде до падения на индексе, поэтому её же hasColumn-гвард
 * при повторном прогоне тихо пропустит весь блок целиком (включая индекс и
 * внешний ключ) — то, что она не создала, так и останется не создано, если
 * просто повторить `migrate --force`. Поэтому — отдельная миграция,
 * каждый шаг с собственной независимой проверкой, ничего не вложено
 * внутрь проверки account_id.
 *
 * paid_at сделан nullable — на момент фикса вставка в эту таблицу падала
 * на КАЖДОЙ попытке (из-за отсутствия account_id), так что реальных строк
 * там, вероятнее всего, нет вовсе, но NOT NULL без DEFAULT рискует упасть
 * на ALTER, если что-то всё же есть — nullable безопаснее для живой
 * прод-таблицы с неизвестным содержимым.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('order_payments', 'paid_at')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->dateTime('paid_at')->nullable()->after('cashflow_transaction_id');
            });
        }

        $indexExists = collect(DB::select("SHOW INDEX FROM order_payments WHERE Key_name = 'order_payments_account_id_paid_at_index'"))->isNotEmpty();
        if (!$indexExists) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->index(['account_id', 'paid_at']);
            });
        }

        $fkExists = collect(DB::select(
            "SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'order_payments'
               AND COLUMN_NAME = 'account_id'
               AND REFERENCED_TABLE_NAME = 'accounts'"
        ))->isNotEmpty();
        if (!$fkExists) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->foreign('account_id')
                    ->references('id')
                    ->on('accounts')
                    ->onDelete('restrict')
                    ->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        // Осознанно пусто — это догоняющий фикс дрейфа схемы, откатывать
        // на состояние "было раньше" на проде нечем и незачем.
    }
};
