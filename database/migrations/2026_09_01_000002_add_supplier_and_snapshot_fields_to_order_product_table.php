<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_product', function (Blueprint $table) {
            if (!Schema::hasColumn('order_product', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable()->after('order_id');
                $table->foreign('supplier_id', 'fk_order_product_supplier')
                    ->references('id')->on('suppliers');
            }

            if (!Schema::hasColumn('order_product', 'payment_policy_snapshot')) {
                $table->string('payment_policy_snapshot', 50)->nullable()->after('status');
            }

            if (!Schema::hasColumn('order_product', 'payment_delay_days_snapshot')) {
                $table->integer('payment_delay_days_snapshot')->nullable()->after('payment_policy_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_product', function (Blueprint $table) {
            if (Schema::hasColumn('order_product', 'supplier_id')) {
                $table->dropForeign('fk_order_product_supplier');
                $table->dropColumn('supplier_id');
            }
            if (Schema::hasColumn('order_product', 'payment_delay_days_snapshot')) {
                $table->dropColumn('payment_delay_days_snapshot');
            }
            if (Schema::hasColumn('order_product', 'payment_policy_snapshot')) {
                $table->dropColumn('payment_policy_snapshot');
            }
        });
    }
};
