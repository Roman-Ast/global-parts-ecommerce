<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('supplier_settlement', function (Blueprint $table) {
            // Добавляем связь на suppliers, но legacy-поле supplier (строка) НЕ трогаем.
            $table->unsignedBigInteger('supplier_id')->nullable()->after('supplier');
            $table->index('supplier_id', 'idx_supplier_settlement_supplier_id');

            $table->foreign('supplier_id', 'fk_supplier_settlement_supplier_id')
                ->references('id')
                ->on('suppliers')
                ->onDelete('set null')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_settlement', function (Blueprint $table) {
            $table->dropForeign('fk_supplier_settlement_supplier_id');
            $table->dropIndex('idx_supplier_settlement_supplier_id');
            $table->dropColumn('supplier_id');
        });
    }
};

/*
Что делает миграция:
- Добавляет supplier_id в supplier_settlement для нормальной связки с таблицей suppliers.
- Старый supplier (строка) остается для совместимости с legacy.
- После этого можно:
  1) Создать поставщиков (suppliers) с code=atpr/rssk/...
  2) Один раз замаппить старые записи: supplier_settlement.supplier -> suppliers.code -> supplier_id
*/
