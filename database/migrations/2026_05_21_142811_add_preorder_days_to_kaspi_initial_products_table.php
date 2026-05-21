<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('kaspi_initial_products', function (Blueprint $table) {
            // По умолчанию 0 дней (товар в наличии)
            $table->integer('preorder_days')->default(0)->after('stock');
        });
    }

    public function down()
    {
        Schema::table('kaspi_initial_products', function (Blueprint $table) {
            $table->dropColumn('preorder_days');
        });
    }
};