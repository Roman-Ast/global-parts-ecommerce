<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_returns', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('order_product_id')->nullable();

            $table->foreign('order_product_id')
                ->references('id')
                ->on('order_product')
                ->nullOnDelete();
                
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('customer_phone', 256)->nullable();

            $table->decimal('qty', 12, 2)->default(1.00);
            $table->decimal('sale_price', 14, 2)->default(0.00);

            $table->decimal('customer_refund_amount', 14, 2)->default(0.00);

            $table->decimal('supplier_purchase_price', 14, 2)->nullable();
            $table->decimal('supplier_refund_amount', 14, 2)->nullable();

            $table->decimal('customer_refund_paid', 14, 2)->default(0.00);
            $table->decimal('supplier_refund_received', 14, 2)->default(0.00);

            $table->date('return_date');
            $table->date('customer_refund_date')->nullable();
            $table->date('supplier_refund_date')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->string('reason', 255)->nullable();
            $table->text('comment')->nullable();

            $table->enum('status', [
                'pending',
                'completed',
                'cancelled',
            ])->default('pending')->index();

            $table->enum('supplier_refund_status', [
                'pending',
                'received',
                'not_expected',
            ])->default('pending')->index();

            $table->foreignId('customer_cashflow_transaction_id')
                ->nullable()
                ->constrained('cashflow_transactions')
                ->nullOnDelete();

            $table->foreignId('supplier_cashflow_transaction_id')
                ->nullable()
                ->constrained('cashflow_transactions')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('return_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_returns');
    }
};