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
        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('supplier_payment_id');
            $table->unsignedBigInteger('supplier_settlement_id'); // закрываем конкретное начисление (realization)
            $table->decimal('amount', 14, 2);

            $table->timestamps();

            $table->index(['supplier_payment_id']);
            $table->index(['supplier_settlement_id']);

            $table->foreign('supplier_payment_id')
                ->references('id')
                ->on('supplier_payments')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('supplier_settlement_id')
                ->references('id')
                ->on('supplier_settlement')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_allocations');
    }
};

/*
Что делает миграция:
- Создает supplier_payment_allocations — распределение оплат по начислениям supplier_settlement.
- Благодаря этому можно прозрачно считать кредиторку:
  долг = ABS(supplier_settlement.sum) - SUM(allocations.amount)
*/
