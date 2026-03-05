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
        Schema::create('cashflow_transactions', function (Blueprint $table) {
            $table->id();

            $table->dateTime('txn_at');
            $table->enum('direction', ['in', 'out']);  // in = поступление, out = расход
            $table->unsignedBigInteger('account_id');
            $table->decimal('amount', 14, 2);

            $table->string('category', 100);           // Sale / Expense / Supplier payment / Supplier refund ...
            $table->string('subcategory', 100)->nullable(); // Fuel / Tax / Owner withdraw / Rent ...
            $table->string('counterparty', 150)->nullable(); // клиент/поставщик/гос-во и т.д.

            $table->string('related_table', 64)->nullable(); // order_payments / supplier_payments / manual ...
            $table->unsignedBigInteger('related_id')->nullable();

            $table->string('comment', 500)->nullable();
            $table->timestamps();

            $table->index(['txn_at']);
            $table->index(['account_id', 'txn_at']);
            $table->index(['category', 'txn_at']);
            $table->index(['related_table', 'related_id']);

            $table->foreign('account_id')
                ->references('id')
                ->on('accounts')
                ->onDelete('restrict')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashflow_transactions');
    }
};

/*
Что делает миграция:
- Создает cashflow_transactions — единый реестр ДДС (все движения денег).
- Сюда должны попадать:
  * продажи (in)
  * расходы (out)
  * оплаты поставщикам (out)
  * возвраты от поставщиков (in)
- На этой таблице строится дашборд и остатки по счетам.
*/
