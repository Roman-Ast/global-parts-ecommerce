<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'delivery_type')) {
                $table->string('delivery_type', 20)->default('delivery')->after('sale_channel');
            }
            if (!Schema::hasColumn('orders', 'city')) {
                $table->string('city', 255)->nullable()->after('delivery_type');
            }
            if (!Schema::hasColumn('orders', 'address')) {
                $table->string('address', 500)->nullable()->after('city');
            }
            if (!Schema::hasColumn('orders', 'vin')) {
                $table->string('vin', 17)->nullable()->after('address');
            }
            if (!Schema::hasColumn('orders', 'comment')) {
                $table->text('comment')->nullable()->after('vin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['delivery_type', 'city', 'address', 'vin', 'comment'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
