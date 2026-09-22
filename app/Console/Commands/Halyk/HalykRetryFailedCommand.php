<?php

namespace App\Console\Commands\Halyk;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Повторяет halyk:create-card для позиций, ранее упавших с error/failed/
 * expired/deleted (по прямому указанию Романа 2026-09-16 — "если что-то
 * упало с ошибкой — гоняй снова", полный карт-бланш на массовый ночной
 * прогон). pickCandidates() в halyk:create-card исключает артикул из
 * будущих батчей НАВСЕГДА, независимо от статуса — --article=X
 * специально обходит это исключение (см. докблок pickCandidates), это и
 * есть штатный механизм точечного повтора, просто здесь он вызывается
 * циклом по всем накопившимся кандидатам разом, не вручную по одному.
 *
 * 'deleted' ДОБАВЛЕН в ретрай 2026-09-17 (Роман, глядя на реальный
 * кабинет Halyk: "их тоже можно отследить и попробовать уже не
 * создавать, а привязывать, потому что таких было много и до этого") —
 * `processCard()` и так делает searchSku ПЕРЕД созданием, и если
 * находит совпадение — биндит, а не создаёт; проблема раньше была
 * именно в том, что поиск иногда НЕ находил существующую карточку с
 * первого раза (слабый поиск/временный 504 на их стороне, см. раздел
 * про 504 в CLAUDE.md) — так и появлялись сами эти 'deleted'. Стоит
 * пробовать искать повторно: если в этот раз найдёт — привяжет, если
 * снова не найдёт — упадёт в тот же `draft_with_product_code_already_exists`
 * при попытке создать (задокументированная блокировка
 * merchantProductCode на их стороне), безвредно, просто трата попытки.
 * `bindToExisting()` — тот же метод, что уже используется в processCard(),
 * ничего нового писать не пришлось.
 * 'reject' по-прежнему не трогаем — реальный отказ модерации по
 * содержанию, без правки данных/маппинга атрибутов повтор просто
 * повторит тот же отказ.
 */
class HalykRetryFailedCommand extends Command
{
    protected $signature = 'halyk:retry-failed {--limit=500}';

    protected $description = 'Повторяет halyk:create-card --article=... для позиций в статусах error/failed/expired/deleted';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        // --article=X в halyk:create-card фильтрует parts_catalog ТОЛЬКО по
        // article, без бренда (2131+ артикулов в таблице встречаются под
        // разными брендами — сама строка article не уникальна). Значит
        // ретрай по голому article рискует зацепить и брендовый вариант,
        // который уже успешно ушёл в bound/success под ДРУГИМ брендом —
        // отправили бы дубль вникуда. Подстраховка: ретраим артикул только
        // если ВСЕ его исторические строки — неудачные, ни одной "хорошей".
        $badArticles = DB::table('halyk_created_cards')
            ->whereIn('status', ['error', 'failed', 'expired', 'deleted'])
            ->pluck('article')
            ->unique();

        $goodStatuses = ['bound', 'success', 'moderation', 'submitted', 'dry_run', 'dry_run_bind'];
        $articlesWithGoodHistory = DB::table('halyk_created_cards')
            ->whereIn('article', $badArticles)
            ->whereIn('status', $goodStatuses)
            ->pluck('article')
            ->unique();

        $articles = $badArticles->diff($articlesWithGoodHistory)->take($limit);

        if ($articles->isEmpty()) {
            $this->info('Нечего повторять — нет позиций в error/failed/expired.');
            return 0;
        }

        $this->info("Повторяем {$articles->count()} артикулов...");

        foreach ($articles as $article) {
            Artisan::call('halyk:create-card', [
                '--article' => $article,
                '--limit'   => 5,
            ]);
            $this->line(trim(Artisan::output()));
        }

        return 0;
    }
}
