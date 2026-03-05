<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cashflow_transactions', function (Blueprint $table) {

            // Category of transaction
            $table->foreignId('cashflow_category_id')
                ->after('direction')
                ->constrained('cashflow_categories')
                ->restrictOnDelete();

            // Expense category (only for expense transactions)
            $table->foreignId('expense_category_id')
                ->nullable()
                ->after('cashflow_category_id')
                ->constrained('expense_categories')
                ->nullOnDelete();

            // Supplier (optional)
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('expense_category_id')
                ->constrained('suppliers')
                ->nullOnDelete();

            // Employee / user who created the transaction (optional)
            $table->foreignId('user_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cashflow_transactions', function (Blueprint $table) {
            $table->dropForeign(['cashflow_category_id']);
            $table->dropForeign(['expense_category_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['user_id']);

            $table->dropColumn([
                'cashflow_category_id',
                'expense_category_id',
                'supplier_id',
                'user_id',
            ]);
        });
    }
};
