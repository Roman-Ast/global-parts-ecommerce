<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Клиенту в поиске на сайте видна только обезличенная метка склада
 * (напр. "ast"/"москва") — намеренно, см. защита от раскрытия
 * поставщиков через Network-инспекцию конкурентами. Но при заказе
 * самим клиентом через сайт (OrderController::store()) в order_product
 * до сих пор попадала только эта же обезличенная метка (fromStock) —
 * админ в своей же панели видел то же самое, что и клиент, хотя должен
 * видеть реального поставщика, как в заказах, оформленных вручную.
 *
 * Реальный supplier_name браузер клиента никогда не получает (это и
 * есть смысл защиты) — значит его нельзя просто "прокинуть" из формы.
 * Вместо этого сервер сам, в момент добавления в корзину
 * (GlobalProductController::addToCartApi), по best-effort ищет
 * реального поставщика в supplier_offers по article+brand+price
 * (закуп) и сохраняет сюда — только для отображения в админке.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('order_product', 'admin_supplier_name')) {
            Schema::table('order_product', function (Blueprint $table) {
                $table->string('admin_supplier_name', 255)->nullable()->after('fromStock');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_product', 'admin_supplier_name')) {
            Schema::table('order_product', function (Blueprint $table) {
                $table->dropColumn('admin_supplier_name');
            });
        }
    }
};
