<?php

namespace App\Console\Commands\Ozon;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Повторяет ozon:create-card --article=... для позиций, ранее упавших с
 * error/failed — по образцу halyk:retry-failed (Роман 2026-09-22,
 * "замерло, надо разобраться"). Живой разбор нашёл РЕАЛЬНУЮ причину
 * массового простоя: 19496 из 31980 строк ozon_created_cards (61%!) были
 * НЕ настоящими постоянными отказами, а тремя видами транзиентных ошибок,
 * навсегда заблокированных из будущих прогонов, потому что у Ozon (в
 * отличие от Halyk) до сих пор не было retry-механизма:
 *
 *   - `periodic_limit_exceeded` (9686 строк, 2026-09-11..19) — суточный
 *     лимит на создание, сбрасывается каждый день. ЧАСТЬ этих строк —
 *     на самом деле маскировка бага с валютой ниже (см. докблок
 *     OzonCreateCardCommand::currency_code) — оба случая одинаково
 *     безопасно повторить сейчас.
 *   - `TOTAL_CREATE_LIMIT_EXCEEDED` (8288 строк, 2026-09-11..15) — упор в
 *     лимит 3001 карточки на СТАРОМ договоре, до переезда на ОМК
 *     16.09.2026. На новом аккаунте (OZON_SELLER_ID=5884954) это
 *     ограничение не действует вообще, строки полностью устарели.
 *   - `currency_differs_from_contract` (1522 строки, 19.09 18:35-19:15) —
 *     реальный код-баг (команда слала RUB вместо KZT после переезда на
 *     ОМК), найден и исправлен в тот же день (см. докблок
 *     OzonCreateCardCommand) — сейчас безопасно повторить.
 *
 * `missing_required_attr:*` и `category_not_found` — НЕ ретраим: это
 * настоящие пробелы в данных/маппинге категорий, без правки исходных
 * данных попытка повторится с тем же результатом, только зря сожжёт
 * кандидата и время на API-вызовы.
 */
class OzonRetryFailedCommand extends Command
{
    protected $signature = 'ozon:retry-failed {--limit=500}';

    protected $description = 'Повторяет ozon:create-card --article=... для позиций в error/failed, кроме заведомо постоянных причин (missing_required_attr/category_not_found)';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        // --article=X в ozon:create-card фильтрует parts_catalog ТОЛЬКО по
        // article, без бренда (та же ситуация, что и у Halyk — артикул не
        // уникален сам по себе). Ретраим артикул, только если ВСЕ его
        // исторические строки — неудачные, ни одной "хорошей" — иначе
        // рискуем задублировать уже успешно отправленный бренд-вариант.
        $badArticles = DB::table('ozon_created_cards')
            ->whereIn('status', ['failed', 'error'])
            ->where(function ($q) {
                $q->whereNull('skip_reason')
                    ->orWhere(function ($q2) {
                        $q2->where('skip_reason', 'not like', 'missing_required_attr:%')
                            ->where('skip_reason', '!=', 'category_not_found');
                    });
            })
            ->pluck('article')
            ->unique();

        $goodStatuses = ['imported', 'submitted', 'dry_run'];
        $articlesWithGoodHistory = DB::table('ozon_created_cards')
            ->whereIn('article', $badArticles)
            ->whereIn('status', $goodStatuses)
            ->pluck('article')
            ->unique();

        $articles = $badArticles->diff($articlesWithGoodHistory)->take($limit);

        if ($articles->isEmpty()) {
            $this->info('Нечего повторять — нет позиций в error/failed с транзиентной причиной.');
            return 0;
        }

        $this->info("Повторяем {$articles->count()} артикулов...");

        foreach ($articles as $article) {
            Artisan::call('ozon:create-card', [
                '--article' => $article,
                '--limit' => 5,
            ]);
            $this->line(trim(Artisan::output()));
        }

        return 0;
    }
}
