<?php

namespace App\Console\Commands\Ozon;

use App\Models\PartsCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Создаёт НОВЫЕ карточки на Ozon строго от Rossko/Shatem в конкурентных
 * по спросу категориях (просьба Романа 2026-10-04/05, продолжение
 * чистки каталога — см. CLAUDE.md, раздел "Чистка каталога под
 * Rossko/Shatem"). После вчерашней архивации ~1122 мёртвых слотов
 * активных карточек осталось ~1,2к — Роман попросил добирать НОВЫМИ
 * карточками, а не продолжать пытаться подменять контент старых (та
 * стратегия почти не работает на мигрированных по ОМК карточках —
 * Ozon блокирует смену бренда/категории, см. docблок
 * OzonReplaceDeadCardsCommand). Для ГЕНУИННО новых offer_id это
 * ограничение не действует — лок касается только уже существующих
 * (перенесённых) product_id, не создания с нуля.
 *
 * Наследует OzonCreateCardCommand ради processCard()/handle() один в
 * один — разница ТОЛЬКО в pickCandidates(): вместо глобального
 * "кто дешевле" (SupplierOfferPricer, то самое, что привело к инциденту
 * с бампером от Автотрейд-Алматы) — строго Rossko/Shatem, и только в
 * целевых категориях из реальной аналитики продаж Kaspi (те же 7, что и
 * в OzonReplaceDeadCardsCommand, ремни/цепи ГРМ исключены по прямой
 * просьбе Романа 2026-10-04 — "сложная технически позиция, высокая
 * вероятность отказа").
 */
class OzonCreateRosskoShatemCardCommand extends OzonCreateCardCommand
{
    protected $signature = 'ozon:create-rs-card {--limit=50} {--dry-run} {--category=} {--article=}';

    protected $description = 'Создаёт новые Ozon-карточки строго от Rossko/Shatem в конкурентных категориях';

    const ALLOWED_SUPPLIERS = ['rossko', 'shatem'];

    /** Те же 7 категорий, что и в OzonReplaceDeadCardsCommand — см. докблок там за полным обоснованием выбора. */
    const TARGET_CATEGORIES = [
        'Тормозные диски',
        'Тормозные колодки',
        'Амортизаторы, составляющие',
        'Рычаги и тяги подвески',
        'Ступица колеса, составляющие',
        'Насосы водяного охлаждения',
        'Элементы двигателя',
    ];

    protected function pickCandidates(int $limit, ?string $onlyArticle, ?string $onlyCategory)
    {
        $query = PartsCatalog::query()
            ->where('scrape_status', 'done')
            ->whereNotNull('name')
            ->whereNotNull('images')
            ->where('images', '!=', '[]')
            ->whereNotIn('article', function ($sub) {
                $sub->select('article')->from('ozon_created_cards');
            });

        if ($onlyArticle) {
            $query->where('article', $onlyArticle);
        } else {
            $categories = $onlyCategory ? [$onlyCategory] : self::TARGET_CATEGORIES;
            $query->whereIn('category_group_title', $categories);
        }

        $pool = $query->inRandomOrder()->limit($limit * 5)->get();

        // Жёстко только Rossko/Shatem — не SupplierOfferPricer (глобальный
        // "кто дешевле" без учёта надёжности/региона — см. докблок класса).
        $articles = $pool->pluck('article_normalized')->unique()->values();
        $brands = $pool->pluck('brand_normalized')->unique()->values();

        $rsOffers = DB::table('supplier_offers')
            ->whereIn('supplier_name', self::ALLOWED_SUPPLIERS)
            ->whereIn('sku_normalized', $articles)
            ->whereIn('brand_normalized', $brands)
            ->where('stock', '>', 0)
            ->get()
            ->groupBy(fn ($o) => $o->sku_normalized . '|' . $o->brand_normalized);

        $pool->each(function ($card) use ($rsOffers) {
            $key = $card->article_normalized . '|' . $card->brand_normalized;
            $offers = $rsOffers->get($key);
            if (!$offers) {
                $card->offer = null;
                return;
            }
            $best = $offers->sortBy('purchase_price')->first();
            $card->offer = [
                'purchase_price' => (float) $best->purchase_price,
                'stock' => (int) $best->stock,
                'supplier_name' => $best->supplier_name,
            ];
        });

        return $pool->filter(fn ($c) => $c->offer !== null)->take($limit)->values();
    }
}
