<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Разовая корректировка прода 2026-09-18 — прямое следствие бага,
 * исправленного в AdminPanelController (двойное списание долга у
 * prepaid-поставщиков с зачётом, см. коммит "Fix double-counted debt
 * reduction for prepaid suppliers with credit"). Три поставщика, три
 * разных проявления одного и того же класса проблемы:
 *
 * ТИСС (id=30): реальный заказ на 27250, зачёт 13392 уже корректно
 * списался (начислено упало до 13858), но старый код всё равно
 * автосписал ПОЛНУЮ сумму 27250 со счёта, не учтя зачёт — переплата
 * 13392. Правим саму запись автоплатежа (id=13) на верную сумму 13858,
 * не создаём новую — это тот же самый платёж, просто с неверной суммой.
 *
 * КУЛАН (id=40): Роман по ошибке продублировал автоплатёж вручную —
 * два платежа по 35623 на один и тот же заказ (id=22 — настоящий
 * автоматический, с комментарием; id=25 — ручной дубль, без комментария).
 * Удаляем дубль.
 *
 * АВТОТРЕЙД (id=29): накопившийся ДО фикса исторический дрейф — оплачено
 * (cashflow) больше, чем отражено в отдельном зачёте (supplier_credits),
 * на 2900₸, хотя долга сейчас и так нет (accrued=0). По просьбе Романа
 * подравниваем "Долг" под "Сальдо" (23600) — не трогая историю реальных
 * платежей (cashflow_transactions), а тем же приёмом, что и в фиксе
 * Фаэтона: корректирующая строка в supplier_settlement, которая поднимет
 * accrued на 2900 (без привязки к несуществующему заказу) — тогда
 * paid(26500) - accrued(2900) = 23600, ровно то, что должно быть.
 *
 * Каждый шаг проверяет текущее состояние перед правкой — безопасно
 * перезапускать, и безопасно для окружений, где этих же ID/сумм нет
 * (напр. локалка — там своя история платежей с другими ID).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ТИСС — правим сумму автоплатежа, не создаём новую запись.
        DB::table('cashflow_transactions')
            ->where('id', 13)
            ->where('supplier_id', 30)
            ->where('amount', 27250.00)
            ->where('comment', 'like', '%Автооплата предоплатному поставщику%')
            ->update(['amount' => 13858.00]);

        // КУЛАН — удаляем ручной дубль автоплатежа (без комментария,
        // та же сумма, что и у настоящего автоматического).
        DB::table('cashflow_transactions')
            ->where('id', 25)
            ->where('supplier_id', 40)
            ->where('amount', 35623.00)
            ->whereNull('comment')
            ->delete();

        // АВТОТРЕЙД — корректирующее начисление на 2900, чтобы "Долг"
        // (paid-accrued) сошёлся с "Сальдо" (supplier_credits=23600).
        // Проверяем текущий разрыв перед вставкой — идемпотентно.
        $accrued = (float) DB::table('supplier_settlement')
            ->where('supplier_id', 29)
            ->where('operation', 'realization')
            ->sum(DB::raw('`sum` * -1'));

        $paid = (float) DB::table('cashflow_transactions')
            ->where('direction', 'out')
            ->where('cashflow_category_id', 3)
            ->where('supplier_id', 29)
            ->sum('amount');

        $creditBalance = (float) DB::table('supplier_credits')->where('supplier_id', 29)->sum('amount');

        $targetAccrued = round($paid - $creditBalance, 2); // такой accrued даст paid-accrued=creditBalance
        $gap = round($targetAccrued - $accrued, 2);

        if (abs($gap) >= 1) {
            DB::table('supplier_settlement')->insert([
                'order_id' => null,
                'product_id' => null,
                'supplier' => 'Автотрейд',
                'supplier_id' => 29,
                'sum' => -$gap, // после *-1 в формуле accrued поднимется ровно на $gap
                'date' => now()->format('Y-m-d'),
                'operation' => 'realization',
                'payment_due_date' => null,
                'payment_status' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Осознанно пусто — разовая корректировка, откатывать нечем/незачем.
    }
};
