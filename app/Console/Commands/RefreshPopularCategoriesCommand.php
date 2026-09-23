<?php

namespace App\Console\Commands;

use App\Models\PartsCatalog;
use App\Models\PopularCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Пересчитывает popular_categories — материализованную замену "живого"
 * GROUP BY на главной странице (см. докблок миграции
 * create_popular_categories_table). Раньше HomeController::index()
 * гонял этот GROUP BY по ВСЕЙ parts_catalog (91к+ строк) на каждый визит
 * на главную — 2.2 сек локально на 50к строк, реальная причина
 * 9-секундной загрузки главной, пойманной Романом 2026-09-23 через
 * DevTools. Сначала завели под часовой Cache::remember(), но Роман
 * явно попросил убрать сам пересчёт с горячего пути вообще ("посчитаем
 * один раз и уберём этот процесс вообще, даже без кеша... редко ж её
 * обновляем эту таблицу") — parts_catalog обновляется только ручными
 * прогонами скрейпинга (parts:scrape-cards), а не каждую минуту.
 *
 * Когда запускать: вручную, после того как прогнали свежий скрейпинг
 * (parts:scrape-cards) и хотите, чтобы новые счётчики/категории
 * отразились на главной. Без этого главная просто продолжит показывать
 * прошлый снимок — не ломается, но и не обновляется сама.
 */
class RefreshPopularCategoriesCommand extends Command
{
    protected $signature = 'catalog:refresh-popular-categories';

    protected $description = 'Пересчитывает popular_categories из parts_catalog (запускать вручную после скрейпинга)';

    public function handle(): int
    {
        $rows = PartsCatalog::query()
            ->where('scrape_status', 'done')
            ->whereNotNull('name')
            ->whereNotNull('category_slug')
            ->selectRaw('category_slug, category_top_title, category_top_code, COUNT(*) as cnt')
            ->groupBy('category_slug', 'category_top_title', 'category_top_code')
            ->orderByDesc('cnt')
            ->limit(20)
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('Нет данных для пересчёта — parts_catalog пуст или ни одной готовой карточки.');
            return 0;
        }

        DB::transaction(function () use ($rows) {
            // delete(), не truncate() — TRUNCATE в MySQL это DDL с неявным
            // COMMIT, ломает транзакцию Laravel ("There is no active
            // transaction" при попытке закоммитить после). Таблица
            // маленькая (десятки строк), DELETE тут не медленнее.
            PopularCategory::query()->delete();
            PopularCategory::insert($rows->map(fn ($row) => [
                'category_slug' => $row->category_slug,
                'category_top_title' => $row->category_top_title,
                'category_top_code' => $row->category_top_code,
                'cnt' => $row->cnt,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());
        });

        $this->info("Пересчитано: {$rows->count()} категорий.");
        return 0;
    }
}
