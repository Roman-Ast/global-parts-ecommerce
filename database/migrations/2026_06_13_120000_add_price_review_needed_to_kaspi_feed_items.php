<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kaspi_feed_items', function (Blueprint $table) {
            $table->tinyInteger('price_review_needed')->default(0)->after('price_strategy');
            $table->string('price_review_reason', 255)->nullable()->after('price_review_needed');
            $table->decimal('price_review_calculated', 12, 2)->nullable()->after('price_review_reason');
        });
    }

    public function down(): void
    {
        Schema::table('kaspi_feed_items', function (Blueprint $table) {
            $table->dropColumn(['price_review_needed', 'price_review_reason', 'price_review_calculated']);
        });
    }
};
