<?php

namespace App\Console\Commands\Halyk;

use App\Models\PartsCatalog;
use App\Services\HalykMarketClient;
use App\Services\SupplierOfferPricer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Пилот: создаёт полноценные новые карточки на Halyk Market из parts_catalog
 * (богатый скрейпинг Kaspi-карточек — фото/описание/характеристики), для
 * позиций, которые реально в наличии (через SupplierOfferPricer, тот же
 * джойн на supplier_offers, что и в публичном каталоге сайта). Причина
 * "только в наличии": обогащать карточку для товара, которого всё равно
 * нет на складе, — чистая работа на конкурентов, которые потом привяжутся
 * к этой же карточке (см. CLAUDE.md, раздел "Halyk Market").
 *
 * Реализовано 2026-08-22 по итогам живого разбора API-доков в тот же день —
 * несколько мест уточнены прямыми запросами к реальным эндпоинтам, а не
 * только по документации (см. комментарии по ходу).
 */
class HalykCreateCardCommand extends Command
{
    protected $signature = 'halyk:create-card {--limit=10} {--article=} {--dry-run}';

    protected $description = 'Пилот: создаёт новые полноценные карточки на Halyk Market из parts_catalog (только в наличии)';

    /**
     * В parts_catalog НЕТ НИ ОДНОЙ строки с весом/габаритами (проверено
     * 2026-08-22 — 0% покрытие по всем 56647 публичным карточкам), а форма
     * создания карточки эти поля запрашивает. Грубые дефолты "среднего
     * мелкого автозапчастья" — временная затычка, не расчёт; если модерация
     * начнёт заворачивать карточки из-за этого — придётся либо считать
     * по категориям, либо вводить руками.
     */
    const DEFAULT_WEIGHT_KG = '0.5';
    const DEFAULT_WIDTH_CM = '15';
    const DEFAULT_HEIGHT_CM = '15';
    const DEFAULT_DEPTH_CM = '15';

    const MAX_PHOTOS = 5;

    public function handle(HalykMarketClient $client): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');
        $onlyArticle = $this->option('article');

        $cards = $this->pickCandidates($limit, $onlyArticle);

        if ($cards->isEmpty()) {
            $this->info('Нечего создавать — либо лимит исчерпан, либо нет позиций в наличии с фото и ещё не обработанных.');
            return 0;
        }

        $this->info("Обрабатываем {$cards->count()} карточек" . ($dryRun ? ' (dry-run, без реальной отправки)' : '') . '...');

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

    /**
     * Кандидаты: parts_catalog ⋈ supplier_offers (через SupplierOfferPricer,
     * тот же матчинг article_normalized/brand_normalized, что и в публичном
     * каталоге сайта) — только реально в наличии, с фото, ещё не
     * обработанные (нет строки в halyk_created_cards).
     */
    private function pickCandidates(int $limit, ?string $onlyArticle)
    {
        $query = PartsCatalog::query()
            ->where('scrape_status', 'done')
            ->whereNotNull('name')
            ->whereNotNull('images')
            ->where('images', '!=', '[]');

        // --article=... — прицельный тест/повтор конкретного артикула,
        // игнорируем историю halyk_created_cards намеренно (иначе
        // --dry-run уже "сжигал" бы слот и следующий реальный прогон на
        // тот же артикул ничего бы не находил).
        if (!$onlyArticle) {
            $already = DB::table('halyk_created_cards')->pluck('article')->all();
            $query->whereNotIn('article', $already);
        }

        if ($onlyArticle) {
            $query->where('article', $onlyArticle);
        }

        // Без сортировки кандидаты шли строго по id — а строки одного
        // бренда/категории лежат в БД подряд (одна партия скрейпа), так
        // что при системном пробеле в характеристиках у конкретного
        // бренда (см. докблок flattenCharacteristics — живой случай
        // 2026-08-24, 1151 SAT-радиатор подряд без "Модель автомобиля")
        // весь --limit мог уйти на один и тот же неудачный участок вместо
        // распределения по всей очереди. inRandomOrder() — на ~56к строк
        // не проблема для батч-команды, не в горячем пути.
        //
        // Берём с запасом (в 5 раз больше лимита) — часть отсеется на
        // attachOffers (нет в наличии ни у одного поставщика).
        $pool = $query->inRandomOrder()->limit($limit * 5)->get();

        $pool = (new SupplierOfferPricer())->attach($pool);

        return $pool->filter(fn ($c) => $c->offer !== null && $c->offer['stock'] > 0)
            ->take($limit)
            ->values();
    }

    private function processCard(HalykMarketClient $client, PartsCatalog $card, bool $dryRun): void
    {
        // 1. Проверка "не существует ли уже" — та же строгая проверка по
        // границе токена, что и в halyk:match (защита от бага BW0010).
        $existing = $client->searchSku(trim("{$card->brand} {$card->article}"), 1, 5);
        if ($this->hasStrictMatch($existing, $card->article)) {
            $this->line('  ⨯ уже есть на Halyk (найдено строгим поиском) — пропуск');
            $this->recordResult($card, status: 'skipped', skipReason: 'already_exists_on_halyk');
            return;
        }

        // 2. Категория 3-го уровня. category_group_title/category_top_title
        // (наши 2 колонки) — это только 2 верхних уровня из полного
        // characteristics.category_path со скрейпинга Kaspi, а у Kaspi
        // путь часто ГЛУБЖЕ (напр. "Свечи зажигания" — уже 3-й уровень
        // Kaspi, отдельно не хранится ни в одной колонке). Именно этот
        // самый глубокий уровень обычно и совпадает по названию с 3-м
        // уровнем Halyk — идём от самого глубокого элемента пути к
        // мелкому, пока не найдём совпадение.
        $categoryId = null;
        foreach ($this->categoryPathQueries($card) as $query) {
            $categoryId = $this->findLevel3Category($client, $query);
            if ($categoryId) {
                break;
            }
        }

        if (!$categoryId) {
            $this->line('  ⨯ категория 3-го уровня не найдена — пропуск');
            $this->recordResult($card, status: 'skipped', skipReason: 'category_not_found');
            return;
        }

        // 3. Бренд.
        $brandId = $this->findBrand($client, $card->brand);
        if (!$brandId) {
            $this->line('  ⨯ бренд не найден — пропуск');
            $this->recordResult($card, status: 'skipped', skipReason: 'brand_not_found', categoryId: $categoryId);
            return;
        }

        // 4. Форма характеристик + попытка заполнить обязательные поля.
        $form = $client->getCharacteristicsForm($categoryId);
        $attrs = $this->buildAttrs($form, $card, $missingRequired);

        if ($missingRequired) {
            $this->line("  ⨯ не удалось заполнить обязательную характеристику «{$missingRequired}» — пропуск");
            $this->recordResult($card, status: 'skipped', skipReason: "missing_required_attr:{$missingRequired}", categoryId: $categoryId, brandId: $brandId);
            return;
        }

        // 5. Фото — скачиваем реальные файлы (Halyk принимает только
        // multipart-загрузку, не ссылки) и грузим им. Требования по их
        // доке (белый фон, квадрат 500-2000px, минимум 3 шт) на практике
        // НЕ проверяются самим upload-эндпоинтом (проверено вживую
        // 2026-08-22 — приняли 403×500, не квадрат, HTTP 200) — грузим
        // что реально есть, дальше смотрим по итогам модерации.
        $media = $this->uploadPhotos($client, $card);
        if (empty($media)) {
            $this->line('  ⨯ не удалось загрузить ни одного фото — пропуск');
            $this->recordResult($card, status: 'skipped', skipReason: 'photo_upload_failed', categoryId: $categoryId, brandId: $brandId);
            return;
        }

        // 6. Сборка payload и отправка на модерацию.
        $payload = [
            'name'        => mb_substr($card->name, 0, 250),
            'category'    => $categoryId,
            'brand'       => $brandId,
            'description' => mb_substr(trim(strip_tags($card->description ?? $card->name)), 0, 3000),
            'attrs'       => $attrs,
            'media'       => $media,
            'weight'      => self::DEFAULT_WEIGHT_KG,
            'width'       => self::DEFAULT_WIDTH_CM,
            'height'      => self::DEFAULT_HEIGHT_CM,
            'depth'       => self::DEFAULT_DEPTH_CM,
            'info'        => [
                'merchantProductCode' => $this->resolveMerchantProductCode($card),
                'pointByCity'         => [[
                    'city' => [
                        'name'   => 'Astana',
                        'nameRu' => 'Астана',
                        'code'   => env('HALYK_CITY_CODE'),
                    ],
                    'price'  => (int) $card->offer['retail_price'],
                    'points' => [[
                        'code'   => env('HALYK_POINT_CODE'),
                        'amount' => (int) $card->offer['stock'],
                    ]],
                ]],
                'loanPeriod' => 3,
            ],
        ];

        if ($dryRun) {
            $this->line('  · dry-run, payload собран, не отправляю: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->recordResult($card, status: 'dry_run', categoryId: $categoryId, brandId: $brandId);
            return;
        }

        $result = $client->submitForModeration($payload);

        if ($result['ok'] && !empty($result['body']['id'])) {
            $this->line("  ✓ отправлено на модерацию, id={$result['body']['id']}");
            $this->recordResult($card, status: 'submitted', categoryId: $categoryId, brandId: $brandId, halykProductId: $result['body']['id']);
        } else {
            $bodyDump = is_array($result['body']) ? json_encode($result['body'], JSON_UNESCAPED_UNICODE) : $result['body'];
            $this->error("  ⨯ отправка не удалась: HTTP {$result['status']} — {$bodyDump}");
            $this->recordResult($card, status: 'failed', skipReason: "HTTP {$result['status']}", categoryId: $categoryId, brandId: $brandId, comment: mb_substr((string) $bodyDump, 0, 1000));
        }
    }

    /**
     * Строгая проверка по границе токена — портировано из halyk:match /
     * ParseKaspiSkuCommand::filterByExactArticle() (защита от бага BW0010).
     */
    private function hasStrictMatch(array $results, string $article): bool
    {
        $normalized = mb_strtoupper(str_replace('-', '', $article));
        $pattern = '/(?<![A-Z0-9])' . preg_quote($normalized, '/') . '(?![A-Z0-9])/u';

        foreach ($results as $r) {
            $nameNormalized = mb_strtoupper(str_replace('-', '', $r['name'] ?? ''));
            if ($nameNormalized !== '' && preg_match($pattern, $nameNormalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Из characteristics.category_path (скрейпинг Kaspi) строит список
     * поисковых запросов от САМОГО ГЛУБОКОГО элемента пути к самому
     * верхнему — глубже = точнее попадание в 3-й уровень Halyk. Дубли
     * (напр. если group_title и последний элемент пути совпадают) не
     * фильтруем специально — findLevel3Category просто ничего не найдёт
     * второй раз, лишний запрос не критичен.
     *
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

    /**
     * GET /category/search возвращает МАССИВ ПУТЕЙ (breadcrumb от 1-го
     * уровня до найденного узла), а не плоский список категорий —
     * проверено вживую 2026-08-22 (изначальное предположение о плоском
     * списке было ошибочным). Разворачиваем все пути и ищем узел level===3.
     */
    private function findLevel3Category(HalykMarketClient $client, ?string $query): ?int
    {
        if (!$query) {
            return null;
        }

        $paths = $client->searchCategory($query, 1, 20);

        foreach ($paths as $path) {
            foreach ($path as $node) {
                if ((int) ($node['level'] ?? 0) === 3) {
                    return (int) $node['id'];
                }
            }
        }

        return null;
    }

    private function findBrand(HalykMarketClient $client, string $brand): ?int
    {
        $results = $client->searchBrand($brand, 1, 10);

        if (empty($results)) {
            return null;
        }

        // Точное совпадение по названию (регистронезависимо) — приоритет
        // над первым попавшимся результатом поиска.
        foreach ($results as $r) {
            if (mb_strtolower(trim($r['name'] ?? '')) === mb_strtolower(trim($brand))) {
                return (int) $r['id'];
            }
        }

        return (int) $results[0]['id'];
    }

    /**
     * Собирает attrs[] для payload из формы характеристик Halyk +
     * characteristics parts_catalog. Формат value в submission — везде
     * СТРОКА (проверено на примере из доки: "Упаковка картонная"/"true"/
     * "5" — включая ENUM, это ИМЯ выбранного варианта, а не
     * classAttrValueId). Если обязательный атрибут не удаётся заполнить —
     * $missingRequired получает имя атрибута по ссылке, вызывающий код
     * должен пропустить карточку целиком.
     */
    private function buildAttrs(array $form, PartsCatalog $card, ?string &$missingRequired): array
    {
        $missingRequired = null;

        // Ответ может быть как один блок {attrs:[...]}, так и массив таких
        // блоков — на момент написания это не было стопроцентно понятно
        // по документации, обрабатываем оба варианта.
        $blocks = array_is_list($form) ? $form : [$form];

        $ourFeatures = $this->flattenCharacteristics($card->characteristics);

        $attrs = [];

        foreach ($blocks as $block) {
            foreach (($block['attrs'] ?? []) as $attr) {
                $attrName = mb_strtolower(trim($attr['attrValue']['name'] ?? ''));
                $required = (bool) ($attr['required'] ?? false);
                $ourValues = $ourFeatures[$attrName] ?? null;

                if ($ourValues === null) {
                    if ($required) {
                        $missingRequired = $attr['attrValue']['name'] ?? $attr['code'] ?? '?';
                        return [];
                    }
                    continue;
                }

                if (($attr['classAttrType'] ?? null) === 'ENUM') {
                    $matchedOption = $this->matchEnumOption($attr['attrValue']['classAttrValues'] ?? [], $ourValues);
                    if ($matchedOption === null) {
                        if ($required) {
                            $missingRequired = $attr['attrValue']['name'] ?? $attr['code'] ?? '?';
                            return [];
                        }
                        continue;
                    }
                    $attrs[] = ['id' => $attr['classAttrAssignmentId'], 'value' => $matchedOption];
                } else {
                    $attrs[] = ['id' => $attr['classAttrAssignmentId'], 'value' => (string) $ourValues[0]];
                }
            }
        }

        return $attrs;
    }

    /**
     * parts_catalog.characteristics — array (Eloquent-каст модели) вида
     * {specifications:[{features:[{name, featureValues:[{value}]}]}]}
     * (скрейпинг с Kaspi). Схлопываем в плоский
     * [имя_характеристики_lowercase => все_значения (array)].
     *
     * Раньше брали только featureValues[0] — одна деталь часто подходит
     * под НЕСКОЛЬКО моделей/годов (напр. Volvo 850/S70/V70 одним
     * радиатором), и если ENUM Halyk не совпадал именно с первым
     * значением, карточка целиком пропускалась как missing_required_attr,
     * хотя второе/третье значение вполне могло совпасть. Найдено живьём
     * 2026-08-24 — весь прогон на 1000 карточек застрял на SAT-радиаторах
     * именно по этой причине (1151 кандидат в очереди, 0 успешных
     * отправок за первые 52 попытки).
     */
    private function flattenCharacteristics(?array $data): array
    {
        if (!$data) {
            return [];
        }

        $flat = [];

        foreach (($data['specifications'] ?? []) as $spec) {
            foreach (($spec['features'] ?? []) as $feature) {
                $name = mb_strtolower(trim($feature['name'] ?? ''));
                $values = array_values(array_filter(array_map(
                    fn($fv) => $fv['value'] ?? null,
                    $feature['featureValues'] ?? []
                ), fn($v) => $v !== null && $v !== ''));

                if ($name !== '' && !empty($values)) {
                    $flat[$name] = $values;
                }
            }
        }

        return $flat;
    }

    /**
     * Ищет среди вариантов ENUM тот, чьё имя совпадает (регистронезависимо,
     * с проверкой вхождения в обе стороны) с ЛЮБЫМ из наших значений
     * характеристики (деталь может подходить под несколько моделей/годов —
     * см. докблок flattenCharacteristics). Возвращает ИМЯ варианта (не
     * classAttrValueId — см. buildAttrs), либо null, если ни одно из наших
     * значений не совпало ни с одним вариантом Halyk.
     */
    private function matchEnumOption(array $options, array $ourValues): ?string
    {
        foreach ($ourValues as $ourValue) {
            $ourLower = mb_strtolower(trim((string) $ourValue));

            foreach ($options as $opt) {
                $optName = trim($opt['name'] ?? '');
                $optLower = mb_strtolower($optName);

                if ($optLower === $ourLower || str_contains($ourLower, $optLower) || str_contains($optLower, $ourLower)) {
                    return $optName;
                }
            }
        }

        return null;
    }

    /**
     * Фото — только реальная загрузка файлов, Halyk ссылки не принимает.
     * Скачиваем то, что есть в parts_catalog.images (обычно 1 шт с Kaspi
     * CDN), максимум MAX_PHOTOS штук.
     *
     * @return array<int, array{id:int, link:string}>
     */
    private function uploadPhotos(HalykMarketClient $client, PartsCatalog $card): array
    {
        $urls = array_slice($card->images ?? [], 0, self::MAX_PHOTOS);

        $files = [];
        foreach ($urls as $i => $url) {
            try {
                $response = Http::timeout(15)->get($url);
                if ($response->ok()) {
                    $files[] = ['name' => "photo_{$i}.jpg", 'contents' => $response->body()];
                }
            } catch (\Throwable $e) {
                // Одно битое фото не должно ронять всю карточку — просто
                // пропускаем его, ниже проверяем, осталось ли хоть одно.
                continue;
            }
        }

        if (empty($files)) {
            return [];
        }

        $uploaded = $client->uploadPhotos($files);

        return array_map(fn ($u) => ['id' => $u['id'], 'link' => $u['assetUrl']], $uploaded);
    }

    /**
     * merchantProductCode для Halyk — по просьбе Романа не выдумываем новую
     * нумерацию, а переиспользуем тот же SKU, что уже используется для
     * этого товара на Kaspi (kaspi_initial_products.sku), чтобы один и тот
     * же физический товар имел один и тот же артикул на обеих площадках —
     * удобно для учёта/выполнения заказов независимо от канала продажи.
     *
     * parts_catalog шире, чем kaspi_initial_products (скрейпинг ЛЮБЫХ
     * карточек Kaspi под наши артикулы, а не только то, что реально
     * продаём сами на Kaspi) — так что совпадение будет не всегда. Если
     * товара на Kaspi у нас нет вовсе — синтетический 8-значный код,
     * детерминированный от id parts_catalog (один и тот же товар всегда
     * получает один и тот же код при повторных запусках, не рандом).
     */
    private function resolveMerchantProductCode(PartsCatalog $card): string
    {
        // Точное совпадение по (sku, brand) — есть составной уникальный
        // индекс kaspi_initial_products_sku_brand_unique, лукап быстрый.
        // Полный скан с нормализацией сюда намеренно не добавлен — 95к
        // строк на каждую карточку без точного совпадения было бы слишком
        // дорого; parts_catalog.article и kaspi_initial_products.sku оба
        // происходят из одного и того же источника (Kaspi), формат должен
        // совпадать в подавляющем большинстве случаев.
        $exact = DB::table('kaspi_initial_products')
            ->where('sku', $card->article)
            ->where('brand', $card->brand)
            ->first();

        if ($exact) {
            return $exact->sku;
        }

        // На Kaspi этого товара у нас нет вовсе (parts_catalog шире,
        // включает любые скрейпленные карточки под наши артикулы, а не
        // только то, что реально продаём сами) — синтетический 8-значный
        // код, детерминированный от id parts_catalog: один и тот же товар
        // всегда получает один и тот же код при повторных запусках.
        return '1' . str_pad((string) $card->id, 7, '0', STR_PAD_LEFT);
    }

    private function recordResult(
        PartsCatalog $card,
        string $status,
        ?string $skipReason = null,
        ?int $categoryId = null,
        ?int $brandId = null,
        ?int $halykProductId = null,
        ?string $comment = null,
    ): void {
        DB::table('halyk_created_cards')->insert([
            'article'           => $card->article,
            'brand'             => $card->brand,
            'parts_catalog_id'  => $card->id,
            'halyk_category_id' => $categoryId,
            'halyk_brand_id'    => $brandId,
            'halyk_product_id'  => $halykProductId,
            'status'            => $status,
            'skip_reason'       => $skipReason,
            'comment'           => $comment,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }
}
