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
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('account_id');

            $table->dateTime('paid_at');
            $table->decimal('amount', 14, 2);
            $table->string('comment', 255)->nullable();

            $table->timestamps();

            $table->index(['order_id', 'paid_at']);
            $table->index(['account_id', 'paid_at']);

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('cascade')
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
        Schema::dropIfExists('order_payments');
    }
};

/*
Что делает миграция:
- Создает order_payments — оплаты клиентов по заказам.
- Решает задачу: на какой счет пришли деньги + частичные/полные оплаты.
- Полная оплата: SUM(order_payments.amount) >= orders.sum_with_margine
*/
