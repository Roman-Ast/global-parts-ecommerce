<?php

namespace App\Console\Commands\Ozon;

use App\Models\PartsCatalog;
use App\Services\OzonClient;
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
 *
 * КРИТИЧЕСКАЯ НАХОДКА 2026-10-04 (живой прогон на ~850 заменах): Ozon
 * отвечает HTTP 200 + task_id на /v3/product/import ДАЖЕ когда реально
 * отказывается менять содержимое — у ВСЕХ карточек аккаунта
 * (перенесённых по договору ОМК 2026-09-16, см. CLAUDE.md) контент
 * заблокирован от смены категории/типа: importStatus() на такой task_id
 * возвращает errors[].code="clone_change_category_not_allowed"
 * ("карточка перенесена из другого кабинета... создайте новую
 * карточку"), а сам товар молча остаётся со СТАРЫМ содержимым. Проверка
 * на выборке 850 "replaced": только 72 (8.5%) реально обновились,
 * 778 (91.5%) — нет, несмотря на видимость успеха (task_id получен).
 * Поэтому ЭТА команда больше не доверяет одному лишь HTTP-accept —
 * после отправки обязательно ждёт и проверяет РЕАЛЬНЫЙ результат через
 * importStatus() (см. verifyAndFinalize()), и помечает слот 'replaced'
 * ТОЛЬКО когда Ozon подтвердил импорт без ошибок.
 *
 * Та же находка показала, что подбор категории напрямую влияет на
 * успех: пары из одной и той же Kaspi-категории (category_group_title)
 * гораздо чаще остаются в ОДНОМ Ozon type_id и проходят проверку, чем
 * случайные пары из разных категорий — поэтому подбор свежих кандидатов
 * теперь идёт СТРОГО по категории исходного мёртвого слота, а не вслепую
 * один-к-одному по двум независимым спискам.
 */
class OzonReplaceDeadCardsCommand extends OzonCreateCardCommand
{
    protected $signature = 'ozon:replace-dead-cards {--limit=20} {--dry-run} {--offer-id= : Точечная замена ОДНОГО конкретного слота вместо автоподбора} {--new-article= : Новый артикул для --offer-id (обязателен вместе с ним)} {--new-brand= : Новый бренд для --offer-id (обязателен вместе с ним)}';

    protected $description = 'Заменяет контент мёртвых Ozon-слотов (нет Rossko/Shatem) на свежие позиции той же категории, с проверкой реального результата импорта';

    const ALLOWED_SUPPLIERS = ['rossko', 'shatem'];

    /** Сколько секунд ждать перед проверкой реального статуса — Ozon устаканивает импорт не мгновенно (документированные ~10 сек, см. OzonCheckCardStatusCommand). */
    const SETTLE_SECONDS = 15;

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

    /**
     * Реальное название категории ремней/цепей ГРМ в parts_catalog — НЕ
     * содержит подстроку "грм" (это жаргонное сокращение, не часть
     * официального названия категории Kaspi), поэтому одного LIKE '%грм%'
     * по category_group_title недостаточно. Найдено живьём 2026-10-04:
     * фоллбэк на "любую категорию кроме ГРМ" (см. pickFreshCandidatesFrom)
     * пропустил 11 позиций именно из этой категории (PATRON PTCK*,
     * Gates K01T313 и т.п.) — отправлены на Ozon ДО того, как это
     * заметили, исправлено отдельно вручную тем же днём.
     */
    const EXCLUDED_CATEGORY_GROUP_TITLES = [
        'Ременной и цепной приводы',
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

        // Подбор СТРОГО по (бренд + категория) исходного слота. Живой тест
        // 2026-10-04 показал, что подбора ТОЛЬКО по категории недостаточно —
        // Ozon блокирует смену категории/типа У ЖЕ смену БРЕНДА отдельно
        // (clone_change_category_not_allowed / clone_change_brand_not_allowed)
        // на всех мигрированных карточках. Единственная комбинация, которая
        // реально проходит — тот же бренд + та же суб-категория (проверено
        // живьём: PATRON-PSA334204 → PATRON PSA339790, оба "амортизатор",
        // подтверждено через importStatus()). Это резко сужает пул (не любой
        // Rossko/Shatem-кандидат подходит, только того же бренда), поэтому
        // реальный процент заполнения будет заметно ниже, чем при слепом
        // подборе — это ограничение платформы Ozon, не огрех в коде.
        $byBrandCategory = $deadSlots->groupBy(fn ($d) => $d->brand . '|' . ($d->category_group_title ?? ''));

        $pending = [];

        foreach ($byBrandCategory as $key => $slotsInGroup) {
            [$brand, $category] = array_pad(explode('|', $key, 2), 2, '');
            $categories = $category === '' ? null : [$category];
            $label = $brand . ' / ' . ($category === '' ? '(без категории)' : $category);

            $fresh = $this->pickFreshCandidatesFrom($slotsInGroup->count(), $categories, brand: $brand);
            $this->info("«{$label}»: мёртвых слотов {$slotsInGroup->count()}, кандидатов того же бренда+категории {$fresh->count()}");

            $pairs = min($slotsInGroup->count(), $fresh->count());
            for ($i = 0; $i < $pairs; $i++) {
                $deadSlot = $slotsInGroup[$i];
                $newCard = $fresh[$i];

                $this->line("→ слот {$deadSlot->offer_id} ({$deadSlot->article}) ⇒ {$newCard->brand} {$newCard->article} — {$newCard->name}");

                try {
                    $submitted = $this->processCard($client, $newCard, $dryRun, forceOfferId: $deadSlot->offer_id);
                } catch (\Throwable $e) {
                    $this->error('  ⨯ исключение: ' . $e->getMessage());
                    continue;
                }

                if (!$submitted) {
                    $this->line('  (слот не тронут, можно подобрать другого кандидата следующим прогоном)');
                    continue;
                }

                if ($dryRun) {
                    continue;
                }

                // processCard() уже записал companion-строку (status=submitted,
                // ozon_task_id) через recordResult() — достаём её, чтобы знать,
                // ПО КАКОМУ task_id проверять реальный результат ниже.
                $companion = DB::table('ozon_created_cards')
                    ->where('offer_id', $deadSlot->offer_id)
                    ->where('status', 'submitted')
                    ->orderByDesc('id')
                    ->first();

                if (!$companion || !$companion->ozon_task_id) {
                    $this->error('  ⨯ не нашёл task_id сразу после отправки — пропускаю проверку для этого слота');
                    continue;
                }

                $pending[] = [
                    'deadSlotId' => $deadSlot->id,
                    'companionId' => $companion->id,
                    'offerId' => $deadSlot->offer_id,
                    'newCard' => $newCard,
                    'taskId' => (int) $companion->ozon_task_id,
                ];
            }

            $leftover = $slotsInGroup->count() - $pairs;
            if ($leftover > 0) {
                $this->line("  (не хватило кандидатов «{$label}» для {$leftover} слотов — попадут в следующий прогон)");
            }
        }

        if ($dryRun) {
            $this->info('--dry-run: ничего не отправлено, проверка результата не нужна.');
            return 0;
        }

        if (empty($pending)) {
            $this->info('Ничего реально не отправлено.');
            return 0;
        }

        $this->info('Отправлено ' . count($pending) . ' — жду ' . self::SETTLE_SECONDS . ' сек, чтобы Ozon обработал импорт, затем проверяю реальный результат...');
        sleep(self::SETTLE_SECONDS);

        $this->verifyAndFinalize($client, $pending);

        return 0;
    }

    /**
     * Опрашивает importStatus() по каждому отправленному task_id и ТОЛЬКО
     * на основании реального ответа Ozon (errors[] пуст → успех) решает,
     * помечать слот 'replaced' или откатывать обратно в 'imported'. Это
     * единственное место, где статус 'replaced' проставляется — см.
     * докблок класса про clone_change_category_not_allowed.
     */
    private function verifyAndFinalize(OzonClient $client, array $pending): void
    {
        $fixed = 0;
        $failed = 0;

        foreach ($pending as $p) {
            try {
                $result = $client->importStatus($p['taskId']);
            } catch (\Throwable $e) {
                $this->error("  ⨯ {$p['offerId']}: не удалось проверить статус — {$e->getMessage()}");
                continue;
            }

            $errors = $result['errors'] ?? [];
            $productId = $result['product_id'] ?? null;

            if (empty($errors)) {
                $newCard = $p['newCard'];
                DB::table('ozon_created_cards')->where('id', $p['deadSlotId'])->update([
                    'article' => $newCard->article,
                    'brand' => $newCard->brand,
                    'parts_catalog_id' => $newCard->id,
                    'ozon_product_id' => $productId,
                    'status' => 'replaced',
                    'comment' => "Контент заменён на {$newCard->brand} {$newCard->article} (2026-10-04, чистка под Rossko/Shatem)",
                    'updated_at' => now(),
                ]);
                // Companion-строка теперь избыточна (дубль того же offer_id,
                // который уже отражён обновлённой строкой мёртвого слота) —
                // убираем, чтобы не плодить два "активных" ряда на один offer_id.
                DB::table('ozon_created_cards')->where('id', $p['companionId'])->delete();
                $this->line("  ✓ {$p['offerId']}: подтверждено, product_id={$productId}");
                $fixed++;
            } else {
                $errorCode = $errors[0]['code'] ?? 'unknown';
                DB::table('ozon_created_cards')->where('id', $p['deadSlotId'])->update([
                    'status' => 'imported',
                    'comment' => "Откат {$errorCode}: Ozon не принял смену содержимого (" . now()->toDateString() . ')',
                    'updated_at' => now(),
                ]);
                // Кандидат не виноват сам по себе (конфликт категории,
                // не дефект данных) — освобождаем его article, чтобы его
                // могли подобрать повторно для СЛОТА ТОЙ ЖЕ категории.
                DB::table('ozon_created_cards')->where('id', $p['companionId'])->delete();
                $this->line("  ⨯ {$p['offerId']}: отклонено Ozon ({$errorCode}) — слот возвращён в imported");
                $failed++;
            }
        }

        $this->info("Проверка завершена: подтверждено={$fixed}, отклонено Ozon={$failed}");
    }

    /** Слоты, у которых НЕТ вообще позиции Rossko/Shatem с тем же article+brand — не просто проигрывают по цене. Несёт category_group_title исходной карточки для подбора замены той же категории. */
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
        })->map(function ($row) use ($cards) {
            $row->category_group_title = $cards->get($row->parts_catalog_id)->category_group_title;
            return $row;
        })->take($limit)->values();
    }

    /**
     * @param array<string>|null $categories null = любая категория (кроме ГРМ, исключено по просьбе Романа).
     * @param string|null $brand СТРОГОЕ совпадение бренда исходного мёртвого слота — см. докблок класса про clone_change_brand_not_allowed.
     */
    private function pickFreshCandidatesFrom(int $limit, ?array $categories, array $excludeArticles = [], ?string $brand = null)
    {
        if ($limit <= 0) {
            return collect();
        }

        $query = PartsCatalog::query()
            ->where('scrape_status', 'done')
            ->whereNotNull('name')
            ->whereNotNull('images')
            ->where('images', '!=', '[]')
            ->whereNotIn('article', function ($sub) {
                $sub->select('article')->from('ozon_created_cards');
            });

        if ($categories !== null) {
            $query->whereIn('category_group_title', $categories);
        } else {
            // whereNotIn на NULL в MySQL/Laravel даёт NULL (не true) — явный
            // orWhereNull нужен, иначе отфильтровались бы и карточки без
            // категории вовсе, не только ремни/цепи ГРМ.
            $query->where(function ($q) {
                $q->whereNotIn('category_group_title', self::EXCLUDED_CATEGORY_GROUP_TITLES)
                    ->orWhereNull('category_group_title');
            })->where('name', 'not like', '%грм%');
        }

        if (!empty($excludeArticles)) {
            $query->whereNotIn('article', $excludeArticles);
        }

        if ($brand !== null) {
            $query->where('brand', $brand);
        }

        $pool = $query->inRandomOrder()->limit($limit * 5)->get();

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
     * же логика надёжности, никаких других поставщиков). Тоже ждёт и
     * проверяет реальный результат — см. докблок класса.
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

        if (!$submitted) {
            return 1;
        }

        if ($dryRun) {
            return 0;
        }

        $companion = DB::table('ozon_created_cards')
            ->where('offer_id', $offerId)
            ->where('status', 'submitted')
            ->orderByDesc('id')
            ->first();

        if (!$companion || !$companion->ozon_task_id) {
            $this->error('  ⨯ не нашёл task_id сразу после отправки — проверь вручную через ozon:check-card-status.');
            return 1;
        }

        $this->line('  Жду ' . self::SETTLE_SECONDS . ' сек и проверяю реальный результат...');
        sleep(self::SETTLE_SECONDS);

        $this->verifyAndFinalize($client, [[
            'deadSlotId' => $deadSlot->id,
            'companionId' => $companion->id,
            'offerId' => $offerId,
            'newCard' => $newCard,
            'taskId' => (int) $companion->ozon_task_id,
        ]]);

        return 0;
    }
}
