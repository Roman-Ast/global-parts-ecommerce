<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kaspi_initial_products', function (Blueprint $table) {
            // Имя поставщика (phaeton, shatem, zakazauto, auto_order)
            if (!Schema::hasColumn('kaspi_initial_products', 'supplier_name')) {
                $table->string('supplier_name')->nullable()->after('brand');
            }
            // Срок доставки/преордер для конкретной позиции
            if (!Schema::hasColumn('kaspi_initial_products', 'preorder_days')) {
                $table->integer('preorder_days')->default(0)->after('stock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kaspi_initial_products', function (Blueprint $table) {
            $table->dropColumn(['supplier_name', 'preorder_days']);
        });
    }
};