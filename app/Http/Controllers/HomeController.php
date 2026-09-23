<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PopularCategory;
use App\Models\Review;
use Auth;

class HomeController extends Controller
{
    /**
     * Иконка плитки категории на главной. Ключ — category_top_code (латинский
     * код из Kaspi-таксономии, стабильный). Категории без записи здесь получают
     * дефолтную иконку — просто добавь файл в public/images/popular-categories/
     * и запись сюда, когда появится подходящая картинка.
     */
    private const CATEGORY_ICONS = [
        'Car chassis' => 'images/main-catalog/main-cat-shockabsorber.jpeg',
        'Engine' => 'images/main-catalog/main-cat-engine-block.jpeg',
        'Brake system' => 'images/main-catalog/main-cat-brakes.jpeg',
        'Transmission' => 'images/main-catalog/main-cat-transmission.jpeg',
        'Cooling system' => 'images/main-catalog/main-cat-coolingsystem.jpeg',
        'Steering' => 'images/main-catalog/main-cat-steering-rack.jpeg',
        'Car filters' => 'images/main-catalog/main-cat-oil-cartridge.jpeg',
        'Car body parts' => 'images/main-catalog/main-cat-bumper.jpeg',
        'Fuel supply system' => 'images/main-catalog/main-catalog-fuel-system.jpeg',
        'Car interior parts' => 'images/main-catalog/main-catalog-interior.jpeg',
        'Car exhaust system' => 'images/main-catalog/mian-cat-exhaust-muffler.jpeg',
        'Heating and air conditioning' => 'images/main-catalog/main-catalog-ac-systems.jpeg',
        'Air Intake Systems' => 'images/main-catalog/main-catalog-air-intake.jpeg',
        'Autoeletrics' => 'images/main-catalog/main-catalog-alternator-electric.jpeg',
    ];

    private const DEFAULT_CATEGORY_ICON = 'images/placeholders/default_gear.jpeg';

    public function index()
    {
        // reviews — таблица всего из 10 захардкоженных строк (подтверждено
        // Романом 2026-09-23), ORDER BY RAND() на таком объёме мгновенный —
        // кэш тут не даёт ничего заметного, но и не мешает, оставлен как есть.
        $reviews = \Illuminate\Support\Facades\Cache::remember('home_random_reviews', 3600, function () {
            return Review::query()
                ->inRandomOrder()
                ->limit(4)
                ->get();
        });

        // popularCategories — раньше GROUP BY по ВСЕЙ parts_catalog (91к+
        // строк) на каждый визит на главную (2.2 сек локально на 50к строк
        // — реальная причина 9-секундной загрузки главной, пойманной
        // Романом 2026-09-23 через DevTools). Сначала завели под часовой
        // Cache::remember(), но Роман явно попросил убрать пересчёт с
        // горячего пути вообще, не полагаясь на кэш ("посчитаем один раз
        // и уберём этот процесс вообще, даже без кеша... редко ж её
        // обновляем эту таблицу") — теперь просто читаем маленькую
        // материализованную popular_categories (см. докблок миграции
        // create_popular_categories_table и команды
        // catalog:refresh-popular-categories). Она наполняется ТОЛЬКО той
        // командой, вручную, после прогона скрейпинга — здесь только чтение.
        $popularCategories = PopularCategory::query()
            ->orderByDesc('cnt')
            ->limit(20)
            ->get()
            ->map(function ($row) {
                $row->icon = self::CATEGORY_ICONS[$row->category_top_code] ?? self::DEFAULT_CATEGORY_ICON;
                return $row;
            });

        return view('index', [
            'reviews' => $reviews,
            'popularCategories' => $popularCategories,
        ]);
    }
}
