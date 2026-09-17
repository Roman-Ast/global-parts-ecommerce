<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('supplier_credits')) {
            Schema::create('supplier_credits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('supplier_id');
                $table->decimal('amount', 14, 2);
                $table->string('source_table', 64)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('comment', 255)->nullable();
                $table->date('date');
                $table->timestamps();

                $table->foreign('supplier_id')->references('id')->on('suppliers');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_credits');
    }
};
