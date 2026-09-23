<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Роман 2026-09-23: Spartex (spartex.kz) нужен как обычный поставщик в
 * ERP (кредиторка/оплаты), по тому же образцу, что и другие заказные
 * агрегаторы — Автопитер/Автозакуп/Radle (те же default_refund_mode=
 * 'account', lead_days/grace_days в том же порядке величины). На сайте
 * Spartex уже подключён с 2026-09-15 как 15-й поставщик прогрессивного
 * поиска (SparePartControllerTest::searchSpartex(), короткий код в UI —
 * "sprtx", см. CLAUDE.md) — code здесь тот же 'sprtx' для единообразия,
 * хотя это разные, не связанные друг с другом системы (тот код — для
 * отображения источника в поиске, этот — для ERP/кредиторки).
 *
 * payment_policy=prepaid — подтверждено Романом явно 2026-09-23 (платит
 * до отгрузки, как Автопитер/Radle).
 *
 * updateOrInsert по name — безопасно запускать повторно.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('suppliers')->updateOrInsert(
            ['name' => 'Spartex'],
            [
                'code' => 'sprtx',
                'payment_policy' => 'prepaid',
                'payment_delay_days' => 0,
                'default_refund_mode' => 'account',
                'lead_days' => 9,
                'grace_days' => 0,
                'is_active' => 1,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        // Осознанно пусто — новый постоянный поставщик, не разовая
        // корректировка данных, откатывать не нужно.
    }
};
