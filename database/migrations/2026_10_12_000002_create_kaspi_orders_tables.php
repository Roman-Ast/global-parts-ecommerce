<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Заказы Kaspi через Merchant API (2026-10-12) + снимок карточки на момент
 * заказа. Цель Романа — отчёт «почему продаём» без слепых зон: позицию на
 * карточке (цена/доставка/кол-во продавцов) фиксируем сразу, когда заказ
 * пришёл, а не берём из редкого парсинга всего каталога.
 *
 * kaspi_orders / kaspi_order_entries — копия того, что отдал API.
 * entries.kaspi_sku = offer.code = sku оффера в нашем XML-фиде = Kaspi SKU
 * карточки (проверено живьём: offer.code 149827228, product id в base64 —
 * то же число). Телефон клиента API отдаёт замаскированным.
 *
 * kaspi_card_snapshots — все продавцы карточки (ВКЛЮЧАЯ нас) на момент
 * снимка + посчитанная наша позиция. reason: order (по заказу) / hot
 * (плановый парсинг горячих) / manual.
 *
 * orders.kaspi_order_code — связь нашего заказа (ERP) с заказом Kaspi,
 * проставляется, когда Роман оформляет заказ из подставленных данных.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kaspi_orders', function (Blueprint $table) {
            $table->id();
            $table->string('kaspi_id')->unique()->comment('id в API (base64)');
            $table->string('code')->unique()->comment('Номер заказа Kaspi, напр. 1106201536');
            $table->string('state')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('kaspi_created_at')->nullable();
            $table->timestamp('planned_delivery_at')->nullable();
            $table->decimal('total_price', 12, 2)->default(0);
            $table->decimal('delivery_cost_for_seller', 12, 2)->default(0);
            $table->string('delivery_mode')->nullable();
            $table->string('payment_mode')->nullable();
            $table->boolean('is_kaspi_delivery')->default(false);
            $table->boolean('pre_order')->default(false);
            $table->string('town')->nullable();
            $table->string('customer_name')->nullable();
            $table->unsignedBigInteger('order_id')->nullable()->index()->comment('Наш orders.id, когда оформлен в ERP');
            $table->json('raw')->nullable();
            $table->timestamps();
        });

        Schema::create('kaspi_order_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kaspi_order_id')->constrained('kaspi_orders')->cascadeOnDelete();
            $table->string('kaspi_entry_id')->unique();
            $table->unsignedInteger('entry_number')->default(0);
            $table->string('kaspi_sku')->index();
            $table->string('offer_name', 500)->nullable();
            $table->string('category')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('base_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->unsignedBigInteger('snapshot_id')->nullable();
            $table->timestamps();
        });

        Schema::create('kaspi_card_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('kaspi_sku')->index();
            $table->string('reason', 20)->default('order');
            $table->unsignedBigInteger('kaspi_order_entry_id')->nullable()->index();
            $table->timestamp('taken_at');
            $table->unsignedSmallInteger('sellers_total')->default(0)->comment('offersCount Kaspi, включая нас');
            $table->boolean('we_present')->default(false);
            $table->decimal('our_price', 12, 2)->nullable();
            $table->string('our_delivery')->nullable();
            $table->unsignedSmallInteger('our_preorder_days')->nullable();
            $table->unsignedSmallInteger('our_price_rank')->nullable()->comment('1 = самые дешёвые (с учётом равенства)');
            $table->unsignedSmallInteger('cheaper_count')->default(0);
            $table->decimal('min_competitor_price', 12, 2)->nullable();
            $table->unsignedSmallInteger('fast_competitors')->default(0)->comment('Сегодня/экспресс/завтра без предзаказа');
            $table->decimal('min_fast_competitor_price', 12, 2)->nullable();
            $table->boolean('we_fastest')->nullable()->comment('Никто из конкурентов не доставляет быстрее нас');
            $table->json('offers')->nullable()->comment('Все продавцы: merchant, цена, доставка, предзаказ, рейтинг');
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('kaspi_order_code')->nullable()->index()->after('sale_channel');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('kaspi_order_code');
        });
        Schema::dropIfExists('kaspi_card_snapshots');
        Schema::dropIfExists('kaspi_order_entries');
        Schema::dropIfExists('kaspi_orders');
    }
};
