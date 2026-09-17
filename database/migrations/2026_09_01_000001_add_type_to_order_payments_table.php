<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('order_payments', 'type')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->string('type', 50)->nullable()->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_payments', 'type')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
