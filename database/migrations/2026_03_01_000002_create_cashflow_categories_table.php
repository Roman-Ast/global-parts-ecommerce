<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cashflow_categories', function (Blueprint $table) {
            $table->id();

            // machine-readable code: sale, expense, supplier_payment, supplier_refund, transfer, owner_withdraw...
            $table->string('code', 64)->unique();

            // human-readable name for UI
            $table->string('name', 150);

            // default direction for this category (hint for UI)
            $table->enum('default_direction', ['in', 'out'])->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflow_categories');
    }
};
