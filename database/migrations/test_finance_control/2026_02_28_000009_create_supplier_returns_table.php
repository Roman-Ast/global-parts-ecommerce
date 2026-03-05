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
        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('supplier_id');

            $table->dateTime('return_at');
            $table->decimal('amount', 14, 2);

            // money_to_account = деньги реально вернулись на счет
            // supplier_balance = деньги не вернулись, а ушли в сальдо/зачет у поставщика
            $table->enum('refund_type', ['money_to_account', 'supplier_balance']);

            $table->unsignedBigInteger('account_id')->nullable(); // если money_to_account
            $table->dateTime('refunded_at')->nullable();          // когда деньги пришли (если пришли)

            $table->string('comment', 255)->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'return_at']);
            $table->index(['account_id', 'refunded_at']);

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
        Schema::dropIfExists('supplier_returns');
    }
};

/*
Что делает миграция:
- Создает supplier_returns — возвраты поставщику и способ возврата денег:
  * на счет (money_to_account) -> это ДДС-поступление (in)
  * на сальдо (supplier_balance) -> денег нет, в ДДС не пишем, но уменьшаем будущие платежи поставщику
*/
