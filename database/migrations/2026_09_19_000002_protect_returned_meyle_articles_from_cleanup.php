<?php

use App\Services\KaspiPriceCalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Разовая корректировка 2026-09-19 — два артикула (1004980114 и
 * 1155230015, оба Meyle, поставщик Phaeton) вернулись от клиента через
 * возврат на Kaspi, но 14 дней на возврат поставщику уже прошли — товар
 * физически у Романа, продавать его нужно, а не терять. Штатный пайплайн
 * (`offers:aggregate`, вызывается из `prices:fetch`) уже успел удалить
 * обе строки из `kaspi_initial_products` и деактивировать соответствующие
 * `kaspi_feed_items` (`removeStaleProducts()` — раз Phaeton перестал их
 * отдавать в прайсе, они попали в "устаревшие" и были вычищены), это
 * ожидаемо для ЛЮБОГО артикула, который поставщик больше не поставляет.
 *
 * До сих пор единственный способ защитить строку от этой чистки —
 * `AggregateSupplierOffersCommand::PROTECTED_SUPPLIER = 'avtozakup'`
 * (спецзначение `supplier_name`), не подходит здесь: `kaspi_feed_items`
 * для этих карточек хранит `supplier_name='phaeton'`, и подмена на
 * 'avtozakup' рассинхронила бы JOIN в
 * `KaspiFeedCleanupCommand` (сверяет `kip.supplier_name = kfi.supplier_name`)
 * — карточки снова ушли бы в "осиротевшие". Вместо этого — отдельный
 * флаг `protected_from_cleanup`, не завязанный на поставщика (см. также
 * правки в `AggregateSupplierOffersCommand`, тот же коммит).
 *
 * Цена/остаток/название взяты из последних живых значений
 * `kaspi_feed_items` до деактивации (единственный сохранившийся источник
 * — сама `kaspi_initial_products` уже пуста для этих артикулов).
 * `purchase_price` — себестоимость, по которой товар уже куплен у
 * Phaeton в первый раз (Роман его не перезакупает, только продаёт
 * физический остаток от возврата) — `price` считаем тем же калькулятором,
 * что и обычный пайплайн, от этой себестоимости. Остаток — по 1 шт.
 * каждого (подтверждено Романом, реальное количество возврата).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('kaspi_initial_products', 'protected_from_cleanup')) {
            Schema::table('kaspi_initial_products', function (Blueprint $table) {
                $table->boolean('protected_from_cleanup')->default(false)->after('supplier_name');
            });
        }

        $items = [
            [
                'sku'            => '1004980114',
                'brand'          => 'meyle',
                'title'          => 'MEYLE Шарнирный Комплект, Приводной Вал 1004980114',
                'purchase_price' => 12852.00,
                'stock'          => 1,
                'supplier_name'  => 'phaeton',
                'preorder_days'  => 0,
            ],
            [
                'sku'            => '1155230015',
                'brand'          => 'meyle',
                'title'          => 'MEYLE Тормозной Диск 1155230015',
                'purchase_price' => 10061.00,
                'stock'          => 1,
                'supplier_name'  => 'phaeton',
                'preorder_days'  => 0,
            ],
        ];

        $now = now();

        foreach ($items as $item) {
            $exists = DB::table('kaspi_initial_products')
                ->where('sku', $item['sku'])
                ->where('brand', $item['brand'])
                ->exists();

            if ($exists) {
                // Уже существует (например, миграция перезапускается,
                // или пайплайн уже успел что-то записать) — просто
                // проставляем защиту, данные не трогаем.
                DB::table('kaspi_initial_products')
                    ->where('sku', $item['sku'])
                    ->where('brand', $item['brand'])
                    ->update(['protected_from_cleanup' => true, 'updated_at' => $now]);
                continue;
            }

            DB::table('kaspi_initial_products')->insert([
                'sku'                     => $item['sku'],
                'title'                   => $item['title'],
                'brand'                   => $item['brand'],
                'category_code'           => null,
                'description'             => null,
                'purchase_price'          => $item['purchase_price'],
                'price'                   => KaspiPriceCalculator::calculate($item['purchase_price']),
                'stock'                   => $item['stock'],
                'images'                  => null,
                'attributes'              => null,
                'raw_cross_numbers'       => null,
                'supplier_name'           => $item['supplier_name'],
                'preorder_days'           => $item['preorder_days'],
                'kaspi_parsed'            => 0,
                'protected_from_cleanup'  => true,
                'created_at'              => $now,
                'updated_at'              => $now,
            ]);
        }

        // Сами карточки kaspi_feed_items (уже существуют, bound=1, просто
        // деактивированы предыдущей чисткой offers:aggregate) — тоже
        // возвращаем в строй. kaspi:feed-cleanup дальше сам подхватит
        // синк stock/purchase_price с kip при следующих прогонах (drift-
        // шаг), но is_active он не трогает вообще — выставляем явно тут,
        // иначе карточки останутся невидимыми в фиде несмотря на то, что
        // kaspi_initial_products для них снова существует.
        //
        // kaspi_qty > 1 карточки (напр. "Диски MEYLE задние 1155230015",
        // id=148817 — продаёт ПАРУ дисков за 1 заказ) намеренно НЕ
        // реактивируем: физически вернулась 1 шт., а kip.stock синкается
        // в kfi.stock как сырое количество единиц, без деления на
        // kaspi_qty (то же поведение у любого другого multi-qty артикула
        // в системе) — реактивация такой карточки продала бы несуществующую
        // вторую штуку.
        DB::table('kaspi_feed_items')
            ->where('supplier_name', 'phaeton')
            ->whereIn('our_article', array_column($items, 'sku'))
            ->where('kaspi_qty', '<=', 1)
            ->update([
                'is_active'  => true,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        // Осознанно пусто — разовая корректировка, откатывать нечем/незачем.
        // Колонку protected_from_cleanup не убираем даже при откате — её
        // теперь читает AggregateSupplierOffersCommand.
    }
};
