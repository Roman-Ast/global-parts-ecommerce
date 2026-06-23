<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kaspi_feed_items', function (Blueprint $table) {
            $table->decimal('purchase_price', 12, 2)->default(0)->after('price');
            $table->decimal('strategic_price', 12, 2)->nullable()->after('purchase_price');
            $table->string('price_strategy', 50)->nullable()->after('strategic_price'); // 'calculator', 'beat_leader', 'mid_market', 'min_margin'
        });
    }

    public function down(): void
    {
        Schema::table('kaspi_feed_items', function (Blueprint $table) {
            $table->dropColumn(['purchase_price', 'strategic_price', 'price_strategy']);
        });
    }
};