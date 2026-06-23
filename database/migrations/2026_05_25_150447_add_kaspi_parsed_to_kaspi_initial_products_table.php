<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kaspi_initial_products', function (Blueprint $table) {
            $table->tinyInteger('kaspi_parsed')->default(0)->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('kaspi_initial_products', function (Blueprint $table) {
            $table->dropColumn('kaspi_parsed');
        });
    }
};
