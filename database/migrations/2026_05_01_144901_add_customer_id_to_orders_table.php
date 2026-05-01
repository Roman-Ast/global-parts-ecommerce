<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Добавляем связь с таблицей клиентов
            //$table->bigInteger('customer_id')->unsigned()->nullable()->after('user_id');
            $table->index('customer_id');
            // Если хочешь жесткую связь на уровне БД (опционально):
            // $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('customer_id');
        });
    }
};
