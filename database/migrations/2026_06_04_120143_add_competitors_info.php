<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kaspi_sku_test', function (Blueprint $table) {
            $table->integer('competitors_min_price')->nullable();
            $table->tinyInteger('competitors_tomorrow_count')->default(0);
            $table->tinyInteger('competitors_total')->default(0);
            $table->integer('kaspi_qty')->nullable();
            $table->tinyInteger('qty_suspicious')->default(0);
            $table->timestamp('competitors_parsed_at')->nullable();
        });

        Schema::table('kaspi_feed_items', function (Blueprint $table) {
            $table->integer('competitors_min_price')->nullable()->after('price');
            $table->tinyInteger('competitors_tomorrow_count')->default(0)->after('competitors_min_price');
            $table->tinyInteger('competitors_total')->default(0)->after('competitors_tomorrow_count');
            $table->integer('kaspi_qty')->nullable()->after('competitors_total');
            $table->tinyInteger('qty_suspicious')->default(0)->after('kaspi_qty');
            $table->timestamp('competitors_parsed_at')->nullable()->after('qty_suspicious');
        });
    }

    public function down(): void
    {
        Schema::table('kaspi_sku_test', function (Blueprint $table) {
            $table->dropColumn([
                'competitors_min_price',
                'competitors_tomorrow_count',
                'competitors_total',
                'kaspi_qty',
                'qty_suspicious',
                'competitors_parsed_at',
            ]);
        });

        Schema::table('kaspi_feed_items', function (Blueprint $table) {
            $table->dropColumn([
                'competitors_min_price',
                'competitors_tomorrow_count',
                'competitors_total',
                'kaspi_qty',
                'qty_suspicious',
                'competitors_parsed_at',
            ]);
        });
    }
};