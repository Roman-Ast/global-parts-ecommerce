<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Дефолт по возврату денег от поставщика при возврате товара клиентом:
     * 'account' — реально возвращают на счёт, 'credit' — оставляют на
     * балансе как зачёт в следующую закупку (напр. Автотрейд). Это только
     * предзаполнение выбора в форме completeCustomerReturn — сам выбор
     * всегда можно переопределить на конкретном возврате, если у
     * поставщика в этот раз иначе.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('default_refund_mode')->nullable()->default('account')->after('payment_delay_days');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('default_refund_mode');
        });
    }
};
