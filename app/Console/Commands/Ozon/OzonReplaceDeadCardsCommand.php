<?php

namespace App\Console\Commands\Ozon;

use App\Models\PartsCatalog;
use App\Services\OzonClient;
use App\Services\SupplierOfferPricer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Замена контента "мёртвых" слотов на Ozon (просьба Романа 2026-10-04,
 * чистка каталога под Rossko/Shatem) — слот считается мёртвым, если у
 * Rossko/Shatem НЕТ позиции с тем же article+brand вообще (не просто
 * проигрывает по цене — тогда это дело OzonFixToRosskoShatemCommand).
 * Для таких слотов нет смысла что-то "чинить" — подбираем СОВСЕМ ДРУГУЮ,
 * свежую позицию от Rossko/Shatem (по категориям из реальной аналитики
 * продаж Kaspi — см. CLAUDE.md/переписку 2026-10-04) и заливаем новый
 * контент (название/фото/категория/атрибуты/цена) ПОД ТЕМ ЖЕ offer_id —
 * слот переиспользуется, не тратится новый (лимит 2500 карточек не
 * трогаем, см. память ozon-total-card-limit-2500).
 *
 * Наследует OzonCreateCardCommand ради processCard()/buildAttributes() и
 * т.д. (категория/атрибуты/фото — та же логика один в один, разница
 * только в ИСТОЧНИКЕ кандидата и в forceOfferId).
 */
class OzonReplaceDeadCardsCommand extends OzonCreateCardCommand
{
    protected $signature = 'ozon:replace-dead-cards {--limit=20} {--dry-run} {--offer-id= : Точечная замена ОДНОГО конкретного слота вместо автоподбора} {--new-article= : Новый артикул для --offer-id (обязателен вместе с ним)} {--new-brand= : Новый бренд для --offer-id (обязателен вместе с ним)}';

    protected $description = 'Заменяет контент мёртвых Ozon-слотов (нет Rossko/Shatem) на свежие позиции по целевым категориям';

    const ALLOWED_SUPPLIERS = ['rossko', 'shatem'];

    /** Категории по убыванию реального спроса на Kaspi (аналитика 2026-10-04) — ремни/цепи ГРМ исключены по просьбе Романа (сложная позиция, высокий процент отказов). */
    const TARGET_CATEGORIES = [
        'Тормозные диски',
        'Тормозные колодки',
        'Амортизаторы, составляющие',
        'Рычаги и тяги подвески',
        'Ступица колеса, составляющие',
        'Насосы водяного охлаждения',
        'Элементы двигателя',
    ];

    public function handle(OzonClient $client): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        // Точечная замена (просьба Романа 2026-10-04 — "если что-то
        // исчезло напишу в консоль, я дам команду, закинешь туда что-то
        // новое") — ozon:sync-price-stock печатает в консоль offer_id
        // пропавших позиций, Роман сам решает чем заменить и просто
        // указывает конкретный новый article/brand, без автоподбора по
        // категориям.
        $forcedOfferId = $this->option('offer-id');
        if ($forcedOfferId) {
            return $this->handleManualReplacement($client, $forcedOfferId, $dryRun);
        }

        $deadSlots = $this->pickDeadSlots($limit);

        if ($deadSlots->isEmpty()) {
            $this->info('Нет мёртвых слотов для замены.');
            return 0;
        }

        $this->info("Мёртвых слотов к обработке: {$deadSlots->count()}");

        $freshCandidates = $this->pickFreshCandidates($deadSlots->count());

        if ($freshCandidates->isEmpty()) {
            $this->info('Нет свежих кандидатов от Rossko/Shatem по целевым категориям.');
            return 0;
        }

        $this->info("Свежих кандидатов нашлось: {$freshCandidates->count()}");

        $pairsCount = min($deadSlots->count(), $freshCandidates->count());

        for ($i = 0; $i < $pairsCount; $i++) {
            $deadSlot = $deadSlots[$i];
            $newCard = $freshCandidates[$i];

            $this->line("→ слот {$deadSlot->offer_id} ({$deadSlot->article}) ⇒ {$newCard->brand} {$newCard->article} — {$newCard->name}");

            try {
                $submitted = $this->processCard($client, $newCard, $dryRun, forceOfferId: $deadSlot->offer_id);

                if ($submitted && !$dryRun) {
                    DB::table('ozon_created_cards')->where('id', $deadSlot->id)->update([
                        'status' => 'replaced',
                        'comment' => "Контент заменён на {$newCard->brand} {$newCard->article} (2026-10-04, чистка под Rossko/Shatem)",
                        'updated_at' => now(),
                    ]);
                } elseif (!$submitted) {
                    // Слот остаётся status=imported (не трогаем) — попадёт в
                    // следующий прогон с другим кандидатом автоматически.
                    $this->line('  (слот не тронут, можно подобрать другого кандидата следующим прогоном)');
                }
            } catch (\Throwable $e) {
                $this->error('  ⨯ исключение: ' . $e->getMessage());
            }
        }

        return 0;
    }

    /** Слоты, у которых НЕТ вообще позиции Rossko/Shatem с тем же article+brand — не просто проигрывают по цене. */
    private function pickDeadSlots(int $limit)
    {
        $rows = DB::table('ozon_created_cards')
            ->where('status', 'imported')
            ->whereNotNull('parts_catalog_id')
            ->whereNotNull('offer_id')
            ->get(['id', 'parts_catalog_id', 'article', 'brand', 'offer_id']);

        $catalogIds = $rows->pluck('parts_catalog_id')->unique();
        $cards = PartsCatalog::whereIn('id', $catalogIds)->get()->keyBy('id');

        $articles = $cards->pluck('article_normalized')->unique()->values();
        $brands = $cards->pluck('brand_normalized')->unique()->values();

        $rsOffers = DB::table('supplier_offers')
            ->whereIn('supplier_name', self::ALLOWED_SUPPLIERS)
            ->whereIn('sku_normalized', $articles)
            ->whereIn('brand_normalized', $brands)
            ->get(['sku_normalized', 'brand_normalized'])
            ->map(fn ($r) => $r->sku_normalized . '|' . $r->brand_normalized)
            ->unique();

        return $rows->filter(function ($row) use ($cards, $rsOffers) {
            $card = $cards->get($row->parts_catalog_id);
            if (!$card) {
                return false;
            }
            $key = $card->article_normalized . '|' . $card->brand_normalized;
            return !$rsOffers->contains($key);
        })->take($limit)->values();
    }

    /** Свежие позиции от Rossko/Shatem в целевых категориях, которых ЕЩЁ НЕТ на Ozon вообще (ни одной записи в ozon_created_cards по этому article). */
    private function pickFreshCandidates(int $limit)
    {
        $pool = PartsCatalog::query()
            ->where('scrape_status', 'done')
            ->whereNotNull('name')
            ->whereNotNull('images')
            ->where('images', '!=', '[]')
            ->whereIn('category_group_title', self::TARGET_CATEGORIES)
            ->whereNotIn('article', function ($sub) {
                $sub->select('article')->from('ozon_created_cards');
            })
            ->inRandomOrder()
            ->limit($limit * 5)
            ->get();

        // Жёстко только Rossko/Shatem — не глобальный "кто дешевле".
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

    /**
     * Точечная замена ОДНОГО конкретного offer_id вручную указанным
     * article+brand — для случая "ozon:sync-price-stock написал, что эта
     * позиция совсем пропала у Rossko/Shatem, Роман сам выбрал чем
     * заменить". Ищет оффер по article+brand СТРОГО у Rossko/Shatem (та
     * же логика надёжности, никаких других поставщиков).
     */
    private function handleManualReplacement(OzonClient $client, string $offerId, bool $dryRun): int
    {
        $newArticle = $this->option('new-article');
        $newBrand = $this->option('new-brand');

        if (!$newArticle || !$newBrand) {
            $this->error('Для --offer-id обязательны --new-article и --new-brand.');
            return 1;
        }

        $deadSlot = DB::table('ozon_created_cards')->where('offer_id', $offerId)->first();
        if (!$deadSlot) {
            $this->error("offer_id={$offerId} не найден в ozon_created_cards.");
            return 1;
        }

        $newCard = PartsCatalog::where('article', $newArticle)->where('brand', $newBrand)->first();
        if (!$newCard) {
            $this->error("Не найдена parts_catalog-карточка article={$newArticle} brand={$newBrand}.");
            return 1;
        }

        $offer = DB::table('supplier_offers')
            ->whereIn('supplier_name', self::ALLOWED_SUPPLIERS)
            ->where('sku_normalized', $newCard->article_normalized)
            ->where('brand_normalized', $newCard->brand_normalized)
            ->where('stock', '>', 0)
            ->orderBy('purchase_price')
            ->first();

        if (!$offer) {
            $this->error("У {$newBrand} {$newArticle} нет оффера Rossko/Shatem в наличии — нечего ставить.");
            return 1;
        }

        $newCard->offer = [
            'purchase_price' => (float) $offer->purchase_price,
            'stock' => (int) $offer->stock,
            'supplier_name' => $offer->supplier_name,
        ];

        $this->line("→ слот {$offerId} ⇒ {$newCard->brand} {$newCard->article} — {$newCard->name}");

        $submitted = $this->processCard($client, $newCard, $dryRun, forceOfferId: $offerId);

        if ($submitted && !$dryRun) {
            DB::table('ozon_created_cards')->where('id', $deadSlot->id)->update([
                'status' => 'replaced',
                'comment' => "Ручная замена на {$newCard->brand} {$newCard->article} (" . now()->toDateString() . ')',
                'updated_at' => now(),
            ]);
        }

        return $submitted ? 0 : 1;
    }
}
