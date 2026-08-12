<?php

namespace App\Http\Controllers;

use App\Console\Commands\SeedOwnPartsCatalogCommand;
use App\Models\PartsCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    /**
     * Иконка плитки группы (подкатегории) внутри категории. Ключ —
     * category_group_slug (стабильный, из parts_catalog). Та же логика, что
     * и HomeController::CATEGORY_ICONS для топ-категорий, только на уровень
     * ниже. Группы без записи здесь получают дефолтную иконку.
     */
    private const GROUP_ICONS = [
        'brake-pads' => 'images/main-catalog/brake-sub-category/Car_brake_pads_render_202608110931.jpeg',
        'brake-discs' => 'images/main-catalog/brake-sub-category/Car_brake_disc_rotor_render_202608110931.jpeg',
        'brake-calipers-and-components' => 'images/main-catalog/brake-sub-category/Car_brake_caliper_product_render_202608110931.jpeg',
        'components-of-the-brake-system' => 'images/main-catalog/brake-sub-category/Car_brake_hardware_kit_render_202608110931.jpeg',
        'brake-hoses' => 'images/main-catalog/brake-sub-category/Car_brake_hose_product_render_202608110931.jpeg',
        'abs-components' => 'images/main-catalog/brake-sub-category/Wheel_speed_sensor_render_202608110931.jpeg',
    ];

    private const DEFAULT_GROUP_ICON = 'images/placeholders/default_gear.jpeg';

    /**
     * Публичный каталог показывает только заполненные карточки — это
     * согласуется с планом по GSC (noindex/исключение пустых карточек из
     * индекса), см. CLAUDE.md, раздел "GSC / SEO — статус".
     */
    private function publishedQuery()
    {
        return PartsCatalog::query()
            ->where('scrape_status', 'done')
            ->whereNotNull('name');
    }

    /**
     * Цена — из живой (обновляется ежедневно пайплайном prices:fetch →
     * offers:aggregate) supplier_offers, а НЕ из global_catalog (там цены
     * зависшие с 2026-05-20, 3 месяца — показывать их как актуальные для
     * "Добавить в корзину" было бы враньём покупателю).
     *
     * Матчинг по (article_normalized, brand_normalized) — тем же generated-
     * колонкам, что и в parts_catalog (см. миграцию
     * 2026_08_10_000002_add_normalized_columns_to_supplier_offers_table).
     * Двойной whereIn — это только сужение кандидатов до нужных карточек
     * (использует индекс), точное попадание пары article+brand проверяется
     * в PHP через составной ключ группировки.
     *
     * $cards мутируется на месте: каждой карточке добавляется ->offer —
     * null, если совпадений у поставщиков нет вовсе, иначе массив с
     * retail_price, stock, supplier_name, preorder_days. stock=0 означает
     * "под заказ" — во вьюхах на это завязан показ кнопки "Добавить в
     * корзину".
     *
     * Наценка — SparePartController::setPrice(), тот же расчёт, что и в
     * обычном поиске на сайте (а не KaspiPriceCalculator!). В калькуляторе
     * для Kaspi зашиты комиссия площадки и логистика Kaspi Доставки, которых
     * нет при прямой продаже с сайта — из-за этого цена в каталоге получалась
     * заметно выше, чем на ту же позицию через обычный поиск, что било по
     * доверию клиента при сравнении. setPrice() ещё и учитывает роль текущего
     * пользователя (обычная/опт/админ), как и везде на сайте — это осознанно,
     * не только для анонимных посетителей.
     */
    private function attachOffers(Collection $cards): Collection
    {
        if ($cards->isEmpty()) {
            return $cards;
        }

        $pricer = new SparePartController();

        $articles = $cards->pluck('article_normalized')->unique()->values();
        $brands = $cards->pluck('brand_normalized')->unique()->values();

        $offersByKey = DB::table('supplier_offers')
            ->select('sku_normalized', 'brand_normalized', 'purchase_price', 'stock', 'supplier_name', 'preorder_days')
            ->whereIn('sku_normalized', $articles)
            ->whereIn('brand_normalized', $brands)
            ->get()
            ->groupBy(fn ($offer) => $offer->sku_normalized . '|' . $offer->brand_normalized);

        foreach ($cards as $card) {
            $matches = $offersByKey->get($card->article_normalized . '|' . $card->brand_normalized);

            if (!$matches) {
                $card->offer = null;
                continue;
            }

            $inStock = $matches->where('stock', '>', 0)->sortBy('purchase_price')->first();
            $best = $inStock ?? $matches->sortBy('purchase_price')->first();

            $card->offer = [
                'purchase_price' => (float) $best->purchase_price,
                'retail_price' => (int) ceil($pricer->setPrice((float) $best->purchase_price)),
                'stock' => (int) $best->stock,
                'supplier_name' => $best->supplier_name,
                'preorder_days' => (int) $best->preorder_days,
            ];
        }

        return $cards;
    }

    /**
     * Бейдж "Выгодно" на самой дешёвой карточке — только среди уже
     * загруженных карточек ТЕКУЩЕЙ страницы (не по всей категории — там
     * могут быть тысячи позиций, отдельный запрос по всей выборке того не
     * стоит) и только на первой странице пагинации (дальше почти никто не
     * доходит). Учитываются только карточки в наличии (stock>0) — бейдж на
     * "под заказ" позиции вводил бы в заблуждение.
     */
    private function markBestPrice(Collection $cards, int $currentPage): Collection
    {
        if ($currentPage !== 1) {
            return $cards;
        }

        $cheapest = $cards
            ->filter(fn ($card) => $card->offer && $card->offer['stock'] > 0)
            ->sortBy(fn ($card) => $card->offer['retail_price'])
            ->first();

        if ($cheapest) {
            $cheapest->isBestPrice = true;
        }

        return $cards;
    }

    /**
     * Топ-уровневая категория: список групп внутри, либо, если у категории
     * нет уровня группы (напр. "Автомобильные фильтры" — сразу лист),
     * список карточек напрямую.
     */
    public function category(string $topSlug)
    {
        $top = PartsCatalog::query()
            ->where('category_slug', $topSlug)
            ->whereNotNull('category_slug')
            ->first(['category_top_code', 'category_top_title', 'category_slug']);

        if (!$top) {
            abort(404);
        }

        $groups = $this->publishedQuery()
            ->where('category_slug', $topSlug)
            ->whereNotNull('category_group_slug')
            ->select('category_group_slug', 'category_group_title', DB::raw('COUNT(*) as cnt'))
            ->groupBy('category_group_slug', 'category_group_title')
            ->orderByDesc('cnt')
            ->get()
            ->map(function ($group) {
                $group->icon = self::GROUP_ICONS[$group->category_group_slug] ?? self::DEFAULT_GROUP_ICON;
                return $group;
            });

        if ($groups->isEmpty()) {
            $cards = $this->publishedQuery()
                ->where('category_slug', $topSlug)
                ->orderByDesc('id')
                ->paginate(48)
                ->withQueryString();

            $this->attachOffers($cards->getCollection());
            $this->markBestPrice($cards->getCollection(), $cards->currentPage());

            return view('catalog.category', [
                'top' => $top,
                'groups' => collect(),
                'cards' => $cards,
            ]);
        }

        return view('catalog.category', [
            'top' => $top,
            'groups' => $groups,
            'cards' => null,
        ]);
    }

    /**
     * Группа (подкатегория) внутри топ-категории — список карточек.
     */
    public function group(string $topSlug, string $groupSlug)
    {
        $top = PartsCatalog::query()
            ->where('category_slug', $topSlug)
            ->whereNotNull('category_slug')
            ->first(['category_top_code', 'category_top_title', 'category_slug']);

        if (!$top) {
            abort(404);
        }

        $cards = $this->publishedQuery()
            ->where('category_slug', $topSlug)
            ->where('category_group_slug', $groupSlug)
            ->orderByDesc('id')
            ->paginate(48)
            ->withQueryString();

        if ($cards->total() === 0) {
            abort(404);
        }

        $this->attachOffers($cards->getCollection());
        $this->markBestPrice($cards->getCollection(), $cards->currentPage());

        $groupTitle = PartsCatalog::query()
            ->where('category_slug', $topSlug)
            ->where('category_group_slug', $groupSlug)
            ->whereNotNull('category_group_title')
            ->value('category_group_title');

        return view('catalog.group', [
            'top' => $top,
            'groupSlug' => $groupSlug,
            'groupTitle' => $groupTitle,
            'cards' => $cards,
        ]);
    }

    /**
     * Публичная карточка товара из parts_catalog (фото, характеристики,
     * применимость). Публичная версия admin.parts-catalog.show — без auth,
     * без сырого JSON-дебага, с SEO-тегами.
     */
    public function show(PartsCatalog $partsCatalog)
    {
        if ($partsCatalog->scrape_status !== 'done' || empty($partsCatalog->name)) {
            abort(404);
        }

        // Краткая сводка для инфо-панели рядом с галереей — берём "важные"
        // фичи (флаг important в самих данных Kaspi), а не хардкодим конкретные
        // названия полей, чтобы работало одинаково для любой категории.
        $quickFacts = collect($partsCatalog->characteristics['specifications'] ?? [])
            ->flatMap(fn ($section) => $section['features'] ?? [])
            ->filter(fn ($feature) => !empty($feature['important']))
            ->map(fn ($feature) => [
                'label' => $feature['name'] ?? '',
                'value' => collect($feature['featureValues'] ?? [])->pluck('value')->join(', '),
            ])
            ->filter(fn ($feature) => $feature['value'] !== '')
            ->take(4)
            ->values();

        $this->attachOffers(collect([$partsCatalog]));

        // Похожие товары — не "с этим товаром покупают" (для этого нет данных
        // о реальных заказах, выдумывать не стали) и не слайдер (лишний JS,
        // риск для GSC/Core Web Vitals — на сайте и так недавно была просадка
        // индексации), а простая статичная сетка.
        //
        // Специально из ДРУГИХ подкатегорий той же топ-категории, не из своей:
        // если человек смотрит тормозной диск, показывать ещё диски бессмысленно
        // (он уже выбрал тип детали) — а вот колодки/суппорта/шланги оттуда же
        // "Тормозной системы" покупают вместе с диском в реальности. Для
        // категорий без подуровня группы (напр. "Автомобильные фильтры") такого
        // деления нет — тогда просто берём из той же топ-категории.
        //
        // Берём пул с запасом (12), а не сразу 6 — часть карточек может не
        // иметь предложений у поставщиков (будет "цена по запросу"); сначала
        // показываем те, что реально можно купить сейчас.
        $related = collect();

        if ($partsCatalog->category_slug) {
            $related = $this->publishedQuery()
                ->where('category_slug', $partsCatalog->category_slug)
                ->where('id', '!=', $partsCatalog->id)
                ->when($partsCatalog->category_group_slug, function ($query) use ($partsCatalog) {
                    $query->where('category_group_slug', '!=', $partsCatalog->category_group_slug);
                })
                ->orderByDesc('id')
                ->limit(12)
                ->get();

            $this->attachOffers($related);

            $related = $related
                ->sortByDesc(fn ($item) => $item->offer && $item->offer['stock'] > 0)
                ->take(6)
                ->values();
        }

        return view('catalog.show', ['card' => $partsCatalog, 'quickFacts' => $quickFacts, 'related' => $related]);
    }

    /**
     * JSON-эндпоинт для иконки "i" в результатах живого поиска
     * (partSearchRes.blade.php) — по клику, не для каждой строки заранее.
     * Раньше фото/характеристики/применимость показывались только для
     * поставщика Gerat (он присылает эти данные прямо в ответе API вместе
     * с ценой). Для остальных поставщиков такого нет — вместо выдумывания
     * интеграции с каждым из них смотрим в свой parts_catalog: если товар
     * там уже отсканирован (с Kaspi), отдаём его фото/описание/характеристики.
     * Матчинг — та же нормализация артикул+бренд, что и везде в каталоге.
     * found=false — это ожидаемый, а не ошибочный ответ (parts_catalog
     * покрывает только твои ~50k карточек, а не полные каталоги сторонних
     * поставщиков) — фронт в этом случае покажет "пока нет информации".
     */
    public function partInfo(Request $request)
    {
        $article = trim((string) $request->query('article', ''));
        $brand = trim((string) $request->query('brand', ''));

        if ($article === '' || $brand === '') {
            return response()->json(['found' => false]);
        }

        $articleNormalized = SeedOwnPartsCatalogCommand::normalizeArticle($article);
        $brandNormalized = SeedOwnPartsCatalogCommand::normalizeBrand($brand);

        $card = PartsCatalog::query()
            ->where('article_normalized', $articleNormalized)
            ->where('brand_normalized', $brandNormalized)
            ->where('scrape_status', 'done')
            ->whereNotNull('name')
            ->first();

        if (!$card) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'name' => $card->name,
            'brand' => $card->brand,
            'article' => $card->article,
            'images' => $card->images ?? [],
            'description' => $card->description,
            'applicability' => $card->applicability,
            'specifications' => $card->characteristics['specifications'] ?? [],
        ]);
    }
}
