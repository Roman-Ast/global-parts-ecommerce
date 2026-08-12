<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartsCatalog;
use Illuminate\Http\Request;

class PartsCatalogController extends Controller
{
    /**
     * Список карточек плитками с пагинацией.
     * Фильтры: source (own/competitor), scrape_status.
     */
    public function index(Request $request)
    {
        $query = PartsCatalog::query()->orderByDesc('id');

        if ($source = $request->query('source')) {
            $query->where('source', $source);
        }

        if ($status = $request->query('status')) {
            $query->where('scrape_status', $status);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('article', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        $cards = $query->paginate(48)->withQueryString();

        // Счётчики для фильтров сверху (по всей таблице, не по текущей выборке)
        $counts = [
            'total' => PartsCatalog::count(),
            'done' => PartsCatalog::where('scrape_status', 'done')->count(),
            'pending' => PartsCatalog::where('scrape_status', 'pending')->count(),
            'not_found' => PartsCatalog::where('scrape_status', 'not_found')->count(),
            'failed' => PartsCatalog::where('scrape_status', 'failed')->count(),
        ];

        return view('admin.parts-catalog.index', compact('cards', 'counts'));
    }

    /**
     * Полная карточка: галерея, описание, применимость, характеристики.
     */
    public function show(PartsCatalog $partsCatalog)
    {
        return view('admin.parts-catalog.show', ['card' => $partsCatalog]);
    }
}