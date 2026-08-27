<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Снимок kaspi_feed_items.price_strategy на момент продажи (только для
 * sale_channel=kaspi) — раньше сценарий репрайсинга (etalon_competitive /
 * beat_tomorrow_competitor / min_margin_dumping / etc.) нигде не сохранялся
 * при создании заказа, и через месяц узнать, в каком сценарии была продана
 * конкретная позиция, можно было только реконструкцией задним числом
 * (сопоставлением текущего снимка kaspi_feed_items с историческими
 * заказами) — ненадёжно, т.к. конкуренты и наш каталог меняются каждый
 * день. С этим полем — честный замер на будущее без реконструкции.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_product', function (Blueprint $table) {
            $table->string('price_strategy')->nullable()->after('deliveryTime');
        });
    }

    public function down(): void
    {
        Schema::table('order_product', function (Blueprint $table) {
            $table->dropColumn('price_strategy');
        });
    }
};
