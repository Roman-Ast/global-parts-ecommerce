<?php

namespace App\Console\Commands\Ozon;

use App\Models\PartsCatalog;
use App\Services\OzonClient;
use App\Services\SupplierOfferPricer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Создаёт новые карточки на Ozon из parts_catalog — по образцу
 * halyk:create-card, но проще: у Ozon одна плоская категория
 * "Запчасти для легковых автомобилей" с ~464 "типами" вместо 3-уровневой
 * категоризации, фото принимаются ПО ССЫЛКЕ (не требуют скачивания и
 * multipart-загрузки, как у Halyk), и создание асинхронное через
 * /v3/product/import + опрос /v1/product/import/info.
 *
 * Живьём проверено 2026-09-03 (см. CLAUDE.md, раздел "Ozon"): категория
 * "Амортизатор подвески" (type_id=970744063) — карточка с реальным фото
 * с Kaspi CDN прошла модерацию с первого раза, статус "Готов к продаже".
 * Единственная засада — валюта аккаунта ЗАФИКСИРОВАНА на RUB (Роман
 * когда-то выбрал её в кабинете, сменить впоследствии нельзя), поэтому
 * цена уходит в рублях с конвертацией по курсу-константе (см.
 * OzonCommissionRates::EXCHANGE_RATE_KZT_TO_RUB — заглушка, не живой курс).
 *
 * Начинаем с ТОЙ ЖЕ категории, что доказанно работает у Halyk
 * ("Амортизаторы", --category=) — та же логика, что и там: сначала
 * доказать пайплайн на одной категории, остальные — по мере разбора
 * attrs-маппинга.
 */
class OzonCreateCardCommand extends Command
{
    protected $signature = 'ozon:create-card {--limit=10} {--article=} {--dry-run} {--category=}';

    protected $description = 'Создаёт новые карточки на Ozon из parts_catalog (только в наличии)';

    /** parts_catalog не содержит веса/габаритов (0% покрытие, см. CLAUDE.md) — те же грубые дефолты, что и у Halyk. */
    const DEFAULT_WEIGHT_G = 500;
    const DEFAULT_DEPTH_MM = 150;
    const DEFAULT_WIDTH_MM = 150;
    const DEFAULT_HEIGHT_MM = 150;

    const MAX_PHOTOS = 5;

    /** Имена атрибутов Ozon, под которые есть специальная обработка (см. buildAttributes()). Проверено вживую на категории "Запчасти для легковых автомобилей". */
    const ATTR_PARTNUMBER = 'Партномер (артикул производителя)';
    const ATTR_TYPE = 'Тип';
    const ATTR_MARKING = 'Нужен код маркировки';
    const ATTR_MODEL_NAME = 'Название модели (для объединения в одну карточку)';
    const ATTR_HS_CODE = 'ТН ВЭД коды ЕАЭС';
    const ATTR_BRAND = 'Бренд';

    public function handle(OzonClient $client): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $onlyArticle = $this->option('article');
        $onlyCategory = $this->option('category');

        $cards = $this->pickCandidates($limit, $onlyArticle, $onlyCategory);

        if ($cards->isEmpty()) {
            $this->info('Нечего создавать — либо лимит исчерпан, либо нет позиций в наличии с фото и ещё не обработанных.');
            return 0;
        }

        $this->info("Обрабатываем {$cards->count()} карточек" . ($dryRun ? ' (dry-run)' : '') . '...');

        foreach ($cards as $card) {
            $this->line("→ {$card->brand} {$card->article} — {$card->name}");

            try {
                $this->processCard($client, $card, $dryRun);
            } catch (\Throwable $e) {
                $this->error('  ⨯ исключение: ' . $e->getMessage());
                $this->recordResult($card, status: 'error', skipReason: mb_substr($e->getMessage(), 0, 250));
            }
        }

        return 0;
    }

    /** Тот же джойн на реальное наличие, что и у Halyk (SupplierOfferPricer). */
    private function pickCandidates(int $limit, ?string $onlyArticle, ?string $onlyCategory)
    {
        $query = PartsCatalog::query()
            ->where('scrape_status', 'done')
            ->whereNotNull('name')
            ->whereNotNull('images')
            ->where('images', '!=', '[]');

        if ($onlyCategory) {
            $query->where('characteristics->category_leaf_title', $onlyCategory);
        }

        if (!$onlyArticle) {
            $already = DB::table('ozon_created_cards')->pluck('article')->all();
            $query->whereNotIn('article', $already);
        } else {
            $query->where('article', $onlyArticle);
        }

        $pool = $query->inRandomOrder()->limit($limit * 5)->get();
        $pool = (new SupplierOfferPricer())->attach($pool);

        return $pool->filter(fn ($c) => $c->offer !== null && $c->offer['stock'] > 0)
            ->take($limit)
            ->values();
    }

    private function processCard(OzonClient $client, PartsCatalog $card, bool $dryRun): void
    {
        // 1. Категория+тип. Ozon не разделяет категорию/тип по нашим 3
        // уровням Kaspi — ищем ЛЮБОЙ узел дерева с type_id, чьё имя
        // пересекается (в любую сторону) с любым элементом нашего пути,
        // от самого глубокого к самому верхнему.
        $resolved = null;
        foreach ($this->categoryPathQueries($card) as $query) {
            $resolved = $this->findTypeInTree($client, $query);
            if ($resolved) {
                break;
            }
        }

        if (!$resolved) {
            $this->line('  ⨯ тип/категория не найдены в дереве Ozon — пропуск');
            $this->recordResult($card, status: 'skipped', skipReason: 'category_not_found');
            return;
        }

        [$categoryId, $typeId, $typeName] = $resolved;

        // 2. Атрибуты.
        $attrsSchema = $client->attributes($categoryId, $typeId);
        $attrs = $this->buildAttributes($client, $categoryId, $typeId, $typeName, $card, $missingRequired);

        if ($missingRequired) {
            $this->line("  ⨯ не удалось заполнить обязательный атрибут «{$missingRequired}» — пропуск");
            $this->recordResult($card, status: 'skipped', skipReason: "missing_required_attr:{$missingRequired}", categoryId: $categoryId, typeId: $typeId);
            return;
        }

        // 3. Фото — по ссылке напрямую, Ozon сам скачивает и перезаливает
        // (проверено вживую 2026-09-03, не нужен файловый аплоад, как у Halyk).
        $images = array_slice($card->images ?? [], 0, self::MAX_PHOTOS);
        if (empty($images)) {
            $this->line('  ⨯ нет фото — пропуск');
            $this->recordResult($card, status: 'skipped', skipReason: 'no_photo', categoryId: $categoryId, typeId: $typeId);
            return;
        }

        // 4. Цена — конвертация KZT→RUB по курсу-константе + наценка на
        // комиссию Ozon, чтобы после её вычета оставалась та же маржа,
        // что и в обычной рознице (см. докблок OzonCommissionRates —
        // оба числа заглушки, ждут подтверждения Романа).
        $priceRub = $this->calculatePriceRub((float) $card->offer['retail_price'], $typeId);

        $payload = [
            'offer_id' => $this->resolveOfferId($card),
            'name' => mb_substr($card->name, 0, 500),
            'description_category_id' => $categoryId,
            'type_id' => $typeId,
            'currency_code' => 'RUB',
            'price' => (string) $priceRub,
            'vat' => '0',
            'images' => $images,
            'weight' => self::DEFAULT_WEIGHT_G,
            'weight_unit' => 'g',
            'dimension_unit' => 'mm',
            'depth' => self::DEFAULT_DEPTH_MM,
            'width' => self::DEFAULT_WIDTH_MM,
            'height' => self::DEFAULT_HEIGHT_MM,
            'attributes' => $attrs,
        ];

        if ($dryRun) {
            $this->line('  · dry-run, payload собран, не отправляю: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->recordResult($card, status: 'dry_run', categoryId: $categoryId, typeId: $typeId);
            return;
        }

        $taskId = $client->importProduct($payload);
        $this->line("  · отправлено, task_id={$taskId}, статус проверяется отдельно (ozon:check-card-status)");
        $this->recordResult($card, status: 'submitted', categoryId: $categoryId, typeId: $typeId, taskId: $taskId);
    }

    /**
     * @return array<int, string>
     */
    private function categoryPathQueries(PartsCatalog $card): array
    {
        $path = $card->characteristics['category_path'] ?? [];
        $titles = array_reverse(array_column($path, 'title'));
        $titles = array_values(array_filter($titles));

        if ($card->category_group_title) {
            $titles[] = $card->category_group_title;
        }
        if ($card->category_top_title) {
            $titles[] = $card->category_top_title;
        }

        return array_values(array_unique($titles));
    }

    /** Только эта ветка дерева — иначе нечёткий поиск ловит совпадения из вообще не автомобильных категорий (живой случай: "Амортизаторы" зацепил велосипедный "Задний амортизатор"). */
    const ROOT_CATEGORY_NAME = 'Автотовары';

    /** Минимальный процент похожести пары слов (similar_text), чтобы считать их совпадением — ниже это уже разные слова, не падежное/числовое окончание. */
    const WORD_SIMILARITY_THRESHOLD = 65.0;

    /**
     * Обходит закэшированное дерево категорий Ozon (только ветку
     * ROOT_CATEGORY_NAME), ищет узел с type_id, чьё имя лучше всего
     * совпадает с запросом ПО НАБОРУ СЛОВ, без учёта порядка — Ozon
     * называет типы в обратном порядке относительно нас (у нас
     * "Тормозные колодки", у них "Колодки тормозные"; у нас "Шаровая
     * опора", у них "Опора шаровая") — сравнение только первого слова
     * (более ранняя версия) пропускало примерно треть категорий именно
     * по этой причине (живой замер 2026-09-04: "Тормозные колодки" 581
     * пропуск, "Тормозные диски" 577, "Шаровая опора" 151 и т.д.).
     * Возвращает [description_category_id родителя, type_id, type_name]
     * либо null.
     *
     * @return array{0:int,1:int,2:string}|null
     */
    private function findTypeInTree(OzonClient $client, ?string $query): ?array
    {
        if (!$query) {
            return null;
        }

        $queryLower = mb_strtolower(trim($query));
        $queryWords = array_values(array_filter(explode(' ', $queryLower)));
        $tree = $client->categoryTree();

        $autoRoot = null;
        foreach ($tree as $node) {
            if (($node['category_name'] ?? null) === self::ROOT_CATEGORY_NAME) {
                $autoRoot = $node['children'] ?? [];
                break;
            }
        }
        if ($autoRoot === null) {
            return null;
        }
        $tree = $autoRoot;

        // Для каждого слова запроса ищем ЛУЧШЕЕ по похожести (similar_text)
        // слово в имени типа (без учёта порядка слов), суммируем лучшие
        // проценты — так "Амортизатор подвески" (слово "амортизатор"
        // почти идентично "амортизаторы", 95%+) обгоняет "Отбойник
        // амортизатора" (то же слово, но с падежным окончанием, 91%) —
        // раньше оба считались "одним совпадением" и порядок обхода
        // дерева решал, кто выиграет, это и было источником ошибок.
        $candidates = [];
        $walk = function (array $nodes, ?int $currentCategoryId) use (&$walk, &$candidates, $queryWords) {
            foreach ($nodes as $node) {
                if (isset($node['type_id'])) {
                    $typeName = trim($node['type_name'] ?? '');
                    $typeWords = array_values(array_filter(explode(' ', mb_strtolower($typeName))));

                    $totalScore = 0.0;
                    $matchedWords = 0;
                    foreach ($queryWords as $qw) {
                        $bestPercent = 0.0;
                        foreach ($typeWords as $tw) {
                            similar_text($qw, $tw, $percent);
                            $bestPercent = max($bestPercent, $percent);
                        }
                        if ($bestPercent >= self::WORD_SIMILARITY_THRESHOLD) {
                            $totalScore += $bestPercent;
                            $matchedWords++;
                        }
                    }

                    // Штраф за специализированные варианты (мото/грузовая/спецтехника) —
                    // наш каталог в основном легковые запчасти, эти уточнения
                    // Ozon подмешиваются к общему поиску и перетягивают счёт
                    // (живой случай: "Тормозные шланги" находило "для мототехники"
                    // вместо обычного варианта).
                    $typeLowerFull = mb_strtolower($typeName);
                    foreach (['мототехник', 'грузовик', 'спецтехник', 'мотоцикл'] as $specialized) {
                        if (str_contains($typeLowerFull, $specialized)) {
                            $totalScore -= 50;
                            break;
                        }
                    }

                    if (!($node['disabled'] ?? false) && $matchedWords > 0) {
                        $candidates[] = ['category' => $currentCategoryId, 'type' => (int) $node['type_id'], 'name' => $typeName, 'score' => $totalScore, 'words' => count($typeWords)];
                    }
                    continue;
                }

                $nextCategoryId = $node['description_category_id'] ?? $currentCategoryId;
                if (!empty($node['children'])) {
                    $walk($node['children'], $nextCategoryId);
                }
            }
        };

        $walk($tree, null);

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score'] ?: $a['words'] <=> $b['words']);
        $best = $candidates[0];

        return [$best['category'], $best['type'], $best['name']];
    }

    /**
     * Собирает attributes[] под /v3/product/import. Обязательные поля,
     * под которые есть специальная обработка — по имени (см. константы
     * ATTR_*), остальные обязательные без обработки -> $missingRequired.
     */
    private function buildAttributes(OzonClient $client, int $categoryId, int $typeId, string $typeName, PartsCatalog $card, ?string &$missingRequired): array
    {
        $missingRequired = null;
        $schema = $client->attributes($categoryId, $typeId);

        $attrs = [];

        foreach ($schema as $attr) {
            $name = trim($attr['name'] ?? '');
            $required = (bool) ($attr['is_required'] ?? false);
            $id = (int) $attr['id'];

            $value = match ($name) {
                self::ATTR_PARTNUMBER => ['value' => $card->article],
                self::ATTR_TYPE => ['dictionary_value_id' => $typeId],
                self::ATTR_MARKING => ['value' => 'false'],
                self::ATTR_MODEL_NAME => ['value' => mb_substr("{$card->brand} {$card->article}", 0, 250)],
                self::ATTR_BRAND => $this->resolveDictionaryValue($client, $categoryId, $typeId, $id, $card->brand),
                self::ATTR_HS_CODE => $this->resolveHsCode($client, $categoryId, $typeId, $id, $typeName),
                default => null,
            };

            if ($value === null) {
                if ($required) {
                    $missingRequired = $name;
                    return [];
                }
                continue;
            }

            $attrs[] = array_merge(['complex_id' => 0, 'id' => $id], ['values' => [$value]]);
        }

        return $attrs;
    }

    /** Поиск бренда в словаре — точное совпадение приоритетнее первого попавшегося. */
    private function resolveDictionaryValue(OzonClient $client, int $categoryId, int $typeId, int $attributeId, string $query): ?array
    {
        $results = $client->searchAttributeValue($categoryId, $typeId, $attributeId, $query);
        if (empty($results)) {
            return null;
        }

        foreach ($results as $r) {
            if (mb_strtolower(trim($r['value'] ?? '')) === mb_strtolower(trim($query))) {
                return ['dictionary_value_id' => (int) $r['id']];
            }
        }

        return ['dictionary_value_id' => (int) $results[0]['id']];
    }

    /**
     * ТН ВЭД — ищем по названию типа товара, предпочитаем короткую запись
     * вида "<код> - Прочие ..." (обобщённый код сразу после дефиса).
     * Проверено вживую: поиск ПОЛНОЙ фразы ("Амортизатор подвески") даёт
     * 0 результатов, а ПЕРВОЕ СЛОВО ("амортизатор") — 9 результатов
     * стабильно на 3 повторных запросах — берём только первое слово типа.
     * Простой str_contains('прочие') оказался НЕДОСТАТОЧЕН: первый же
     * результат поиска — код для ЖЕЛЕЗНОДОРОЖНЫХ локомотивов, в длинном
     * юридическом описании которого слово "прочие" тоже где-то
     * встречается ("тележки... и их части: прочие, включая..."), поэтому
     * ищем именно "прочие" СРАЗУ ПОСЛЕ кода-дефиса, а среди таких
     * совпадений берём самое короткое (менее заужено доп. условиями типа
     * "малолитражных автомобилей").
     */
    private function resolveHsCode(OzonClient $client, int $categoryId, int $typeId, int $attributeId, string $typeName): ?array
    {
        $firstWord = trim(explode(' ', trim($typeName))[0] ?? $typeName);
        $results = $client->searchAttributeValue($categoryId, $typeId, $attributeId, $firstWord);
        if (empty($results)) {
            return null;
        }

        $genericMatches = array_filter($results, fn ($r) => preg_match('/^\d+\s*-\s*прочие/ui', trim($r['value'] ?? '')) === 1);

        if (!empty($genericMatches)) {
            usort($genericMatches, fn ($a, $b) => mb_strlen($a['value']) <=> mb_strlen($b['value']));
            return ['dictionary_value_id' => (int) reset($genericMatches)['id']];
        }

        return ['dictionary_value_id' => (int) $results[0]['id']];
    }

    /**
     * Цена в рублях = (себестоимость в KZT * курс) / (1 - комиссия/100) —
     * то есть после вычета комиссии Ozon остаётся сумма, эквивалентная
     * нашей обычной рознице в KZT. Оба параметра — заглушки
     * (OzonCommissionRates), пересчитать при уточнении.
     */
    private function calculatePriceRub(float $retailPriceKzt, int $typeId): int
    {
        $rub = $retailPriceKzt * OzonCommissionRates::EXCHANGE_RATE_KZT_TO_RUB;
        $commission = OzonCommissionRates::forType($typeId);
        $withCommission = $rub / (1 - $commission / 100);

        return (int) round($withCommission);
    }

    /**
     * offer_id — наш внутренний код товара у Ozon. В отличие от Halyk
     * (где Роман просил НЕ раскрывать структуру через merchantProductCode),
     * тут пока используем article+brand напрямую — offer_id не виден
     * покупателям на витрине Ozon. Если понадобится та же защита, что и
     * у Halyk — заменить на source_kaspi_sku по аналогии.
     */
    private function resolveOfferId(PartsCatalog $card): string
    {
        return mb_substr("{$card->brand}-{$card->article}", 0, 50);
    }

    private function recordResult(
        PartsCatalog $card,
        string $status,
        ?string $skipReason = null,
        ?int $categoryId = null,
        ?int $typeId = null,
        ?int $taskId = null,
        ?string $comment = null,
    ): void {
        DB::table('ozon_created_cards')->insert([
            'article' => $card->article,
            'brand' => $card->brand,
            'parts_catalog_id' => $card->id,
            'ozon_category_id' => $categoryId,
            'ozon_type_id' => $typeId,
            'ozon_task_id' => $taskId,
            'status' => $status,
            'skip_reason' => $skipReason,
            'comment' => $comment,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
