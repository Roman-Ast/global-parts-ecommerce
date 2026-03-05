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
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('account_id');

            $table->dateTime('paid_at');
            $table->decimal('amount', 14, 2);
            $table->string('comment', 255)->nullable();

            $table->timestamps();

            $table->index(['supplier_id', 'paid_at']);
            $table->index(['account_id', 'paid_at']);

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->onDelete('restrict')
                ->onUpdate('cascade');

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
        Schema::dropIfExists('supplier_payments');
    }
};

/*
Что делает миграция:
- Создает supplier_payments — фактические оплаты поставщикам.
- Эти записи должны отражаться в ДДС как расход (direction=out, category='Supplier payment').
*/
