<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Разовая корректировка прода 2026-09-19 — Роман сверил "Рома Kaspi Gold"
 * (account_id — ищем по имени, не хардкодим id) с реальным балансом и
 * попросил выставить 25925. Тот же приём, что и в более ранней
 * корректировке "Рома Kaspi Pay" в этой сессии (там Роман правил вручную
 * через UI, здесь то же самое, но миграцией) — трогаем ТОЛЬКО строку
 * "Входящий остаток" (историческое стартовое сальдо счёта, cashflow_
 * category_id=2), никакие реальные проведённые транзакции не меняем.
 *
 * Считаем текущий баланс счёта живьём (не подставляем ранее посчитанное
 * в переписке значение — с тех пор могли добавиться новые транзакции) и
 * подгоняем сумму опорной строки на разницу — идемпотентно: при повторном
 * запуске текущий баланс уже будет равен целевому, разница 0, ничего не
 * изменится.
 */
return new class extends Migration
{
    public function up(): void
    {
        $account = DB::table('accounts')->where('name', 'Рома Kaspi Gold')->first();

        if (!$account) {
            return;
        }

        $accountId = $account->id;
        $target = 25925.00;

        $totalIn = (float) DB::table('cashflow_transactions')
            ->where('account_id', $accountId)
            ->where('direction', 'in')
            ->sum('amount');

        $totalOut = (float) DB::table('cashflow_transactions')
            ->where('account_id', $accountId)
            ->where('direction', 'out')
            ->sum('amount');

        $currentBalance = round($totalIn - $totalOut, 2);
        $gap = round($target - $currentBalance, 2);

        if (abs($gap) < 0.01) {
            return;
        }

        $openingRow = DB::table('cashflow_transactions')
            ->where('account_id', $accountId)
            ->where('subcategory', 'Входящий остаток')
            ->orderBy('id')
            ->first();

        if ($openingRow) {
            DB::table('cashflow_transactions')
                ->where('id', $openingRow->id)
                ->update([
                    'amount' => round((float) $openingRow->amount + $gap, 2),
                    'updated_at' => now(),
                ]);
        } else {
            // Опорной строки нет вообще (не должно случиться на этом
            // счёте, но на всякий случай) — заводим корректировку явной
            // отдельной строкой, а не молча теряем разницу.
            DB::table('cashflow_transactions')->insert([
                'txn_at' => now()->format('Y-m-d'),
                'direction' => $gap > 0 ? 'in' : 'out',
                'cashflow_category_id' => 2,
                'expense_category_id' => null,
                'supplier_id' => null,
                'user_id' => null,
                'account_id' => $accountId,
                'amount' => abs($gap),
                'subcategory' => 'Входящий остаток',
                'counterparty' => null,
                'related_table' => null,
                'related_id' => null,
                'comment' => 'Корректировка баланса на ' . now()->format('Y-m-d'),
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
