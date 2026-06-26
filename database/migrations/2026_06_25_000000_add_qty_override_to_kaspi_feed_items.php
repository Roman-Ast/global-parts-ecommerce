<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kaspi_feed_items', function (Blueprint $table) {
            $table->integer('qty_override')
                  ->nullable()
                  ->default(null)
                  ->after('kaspi_qty')
                  ->comment('Ручное переопределение кол-ва. Если NULL — используется kaspi_qty');
        });
    }

    public function down(): void
    {
        Schema::table('kaspi_feed_items', function (Blueprint $table) {
            $table->dropColumn('qty_override');
        });
    }
};
