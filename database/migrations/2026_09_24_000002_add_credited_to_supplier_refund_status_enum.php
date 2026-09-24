<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Найдено 2026-09-24 при попытке применить fix_stuck_tiss_return_1 —
 * миграция упала на "Data truncated for column 'supplier_refund_status'".
 * Причина: сама колонка — ENUM('pending','received','not_expected')
 * (2026_03_09_180330_customer_returns.php), но
 * CustomerReturnController::update() пишет туда 'credited' в ветке
 * supplier_refund_mode==='credit' (см. строку "$supplierRefundStatus =
 * 'credited';"). strict-режим MySQL включён в config/database.php —
 * значит эта ветка кода была потенциально сломана с самого начала для
 * ЛЮБОГО возврата с зачётом на баланс, просто, похоже, ни разу не
 * доходила до реального боевого прогона (существующие зачёты вроде
 * Армтека заведены другим путём — форма "Начальные остатки", не через
 * этот контроллер).
 *
 * Добавляем 'credited' в ENUM — единственный минимальный фикс схемы под
 * уже существующую логику приложения, без изменения самого кода
 * контроллера (он и так писал ровно то значение, что нужно).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE customer_returns MODIFY supplier_refund_status ENUM('pending','received','not_expected','credited') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE customer_returns MODIFY supplier_refund_status ENUM('pending','received','not_expected') NOT NULL DEFAULT 'pending'");
    }
};
