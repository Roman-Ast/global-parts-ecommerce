<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('supplier_settlement', 'payment_due_date')) {
            Schema::table('supplier_settlement', function (Blueprint $table) {
                $table->date('payment_due_date')->nullable()->after('operation');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('supplier_settlement', 'payment_due_date')) {
            Schema::table('supplier_settlement', function (Blueprint $table) {
                $table->dropColumn('payment_due_date');
            });
        }
    }
};
