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
        // Важно: мы НЕ используем ->change(), чтобы не требовать doctrine/dbal.
        // Меняем типы через raw SQL.
        //
        // Меняем:
        // - supplier_settlement.sum: DOUBLE -> DECIMAL(14,2) (правильно для денег)
        // - supplier_settlement.date: VARCHAR -> DATE (для отчетов/расчетов due_date)
        //
        // Если date хранится как 'YYYY-MM-DD' — MySQL конвертирует автоматически.
        // Если формат другой — сначала приведи данные к YYYY-MM-DD.
        DB::statement("ALTER TABLE `supplier_settlement` MODIFY `sum` DECIMAL(14,2) NOT NULL");
        DB::statement("ALTER TABLE `supplier_settlement` MODIFY `date` DATE NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `supplier_settlement` MODIFY `sum` DOUBLE NOT NULL");
        DB::statement("ALTER TABLE `supplier_settlement` MODIFY `date` VARCHAR(255) NOT NULL");
    }
};

/*
Что делает миграция:
- Приводит supplier_settlement к корректным типам данных:
  * sum: DECIMAL(14,2) вместо DOUBLE (чтобы не было ошибок округления)
  * date: DATE вместо STRING (чтобы можно было считать кредиторку по датам)
*/
