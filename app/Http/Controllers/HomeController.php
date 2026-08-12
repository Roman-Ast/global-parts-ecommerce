<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PartsCatalog;
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
        $reviews = Review::query()
            ->inRandomOrder()
            ->limit(4)
            ->get();

        $popularCategories = PartsCatalog::query()
            ->where('scrape_status', 'done')
            ->whereNotNull('name')
            ->whereNotNull('category_slug')
            ->selectRaw('category_slug, category_top_title, category_top_code, COUNT(*) as cnt')
            ->groupBy('category_slug', 'category_top_title', 'category_top_code')
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
