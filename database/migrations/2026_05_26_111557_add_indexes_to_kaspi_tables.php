<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = [
            'kaspi_initial_products' => [
                ['columns' => ['sku', 'brand'], 'name' => 'idx_kip_sku_brand'],
                ['columns' => ['supplier_name'],  'name' => 'idx_kip_supplier'],
                ['columns' => ['kaspi_parsed'],   'name' => 'idx_kip_parsed'],
                ['columns' => ['stock'],           'name' => 'idx_kip_stock'],
            ],
            'kaspi_sku_test' => [
                ['columns' => ['request_article'], 'name' => 'idx_kst_article'],
                ['columns' => ['sku'],             'name' => 'idx_kst_sku'],
            ],
        ];

        foreach ($indexes as $tableName => $tableIndexes) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName, $tableIndexes) {
                foreach ($tableIndexes as $idx) {
                    try {
                        $table->index($idx['columns'], $idx['name']);
                    } catch (\Exception $e) {
                        // индекс уже существует — пропускаем
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('kaspi_initial_products', function (Blueprint $table) {
            $table->dropIndex('idx_kip_sku_brand');
            $table->dropIndex('idx_kip_supplier');
            $table->dropIndex('idx_kip_parsed');
            $table->dropIndex('idx_kip_stock');
        });

        Schema::table('kaspi_sku_test', function (Blueprint $table) {
            $table->dropIndex('idx_kst_article');
            $table->dropIndex('idx_kst_sku');
        });
    }
};