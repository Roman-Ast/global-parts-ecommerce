<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Нефильтрованная копия supplier_offers — без отсева по stock>=2/
     * цена>=5000 (те же пороги, что в FetchPricesCommand и
     * AggregateSupplierOffersCommand::MIN_STOCK/MIN_PRICE). Эта таблица не
     * для продажи и не участвует в живом ценообразовании (его по-прежнему
     * считает только supplier_offers через SupplierOfferPricer) — она чисто
     * источник кандидатов для расширенного скрейпа контента Kaspi (GSC),
     * заполняется отдельной командой prices:fetch-raw.
     */
    public function up(): void
    {
        Schema::create('supplier_offers_raw', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->string('supplier_name');
            $table->string('title');
            $table->string('brand');
            $table->decimal('purchase_price', 12, 2);
            $table->integer('stock');
            $table->integer('preorder_days')->default(0);
            $table->timestamps();

            $table->unique(['sku', 'supplier_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_offers_raw');
    }
};
