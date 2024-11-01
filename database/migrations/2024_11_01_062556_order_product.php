<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_product', function (Blueprint $table) {
            $table->id();
            $table->biginteger('order_id')->unsigned()->nullable();
            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('set null')
                ->onUpdate('cascade');
            $table->string('article');
            $table->string('brand');
            $table->string('name');
            $table->string('searched_number');
            $table->integer('fromStock');
            $table->integer('deliveryTime');
            $table->string('deliveryCity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_product');
    }
};
