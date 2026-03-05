<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("DROP VIEW IF EXISTS v_supplier_payables");
        DB::statement("
            CREATE VIEW v_supplier_payables AS
            SELECT
              ss.id AS supplier_settlement_id,
              ss.supplier_id,
              ss.order_id,
              ss.product_id,
              ss.`date` AS accrual_date,
              DATE_ADD(
                DATE_ADD(ss.`date`, INTERVAL COALESCE(s.lead_days,0) DAY),
                INTERVAL COALESCE(s.grace_days,0) DAY
              ) AS due_date,
              ABS(ss.`sum`) AS amount
            FROM supplier_settlement ss
            JOIN suppliers s ON s.id = ss.supplier_id
            WHERE ss.operation = 'realization'
        ");

        DB::statement("DROP VIEW IF EXISTS v_supplier_payables_paid");
        DB::statement("
            CREATE VIEW v_supplier_payables_paid AS
            SELECT
              spa.supplier_settlement_id,
              SUM(spa.amount) AS paid_amount
            FROM supplier_payment_allocations spa
            GROUP BY spa.supplier_settlement_id
        ");

        DB::statement("DROP VIEW IF EXISTS v_supplier_payables_balance");
        DB::statement("
            CREATE VIEW v_supplier_payables_balance AS
            SELECT
              p.*,
              COALESCE(pp.paid_amount, 0) AS paid_amount,
              (p.amount - COALESCE(pp.paid_amount, 0)) AS balance
            FROM v_supplier_payables p
            LEFT JOIN v_supplier_payables_paid pp
              ON pp.supplier_settlement_id = p.supplier_settlement_id
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS v_supplier_payables_balance");
        DB::statement("DROP VIEW IF EXISTS v_supplier_payables_paid");
        DB::statement("DROP VIEW IF EXISTS v_supplier_payables");
    }
};

/*
Что делает миграция:
- Создает VIEW для кредиторки без дублирования данных:
  1) v_supplier_payables — начисления долга + плановая дата оплаты (due_date)
  2) v_supplier_payables_paid — сколько закрыто оплатами
  3) v_supplier_payables_balance — остаток долга (balance)
*/
