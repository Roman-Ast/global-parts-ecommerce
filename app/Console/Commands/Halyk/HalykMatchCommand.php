<?php

namespace App\Console\Commands\Halyk;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Сопоставляет кандидатов halyk_sku_candidates (от halyk:search-sku) с
 * запрошенным артикулом по строгому вхождению с проверкой границы токена —
 * тот же приём, что уже защищал Kaspi-матчинг от бага BW0010 (короткий
 * артикул матчился по substring на карточки вида BW0010-07-2, продажи ниже
 * себестоимости). Портировано 1:1 из
 * ParseKaspiSkuCommand::filterByExactArticle(), просто источник кандидатов
 * другой.
 */
class HalykMatchCommand extends Command
{
    protected $signature = 'halyk:match';

    protected $description = 'Сопоставляет кандидатов halyk_sku_candidates с нашим артикулом (строгое вхождение, без substring-бага)';

    public function handle(): int
    {
        $candidates = DB::table('halyk_sku_candidates')
            ->whereNotNull('halyk_sku_id')
            ->where('matched', false)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('Нет несопоставленных кандидатов.');
            return 0;
        }

        $matchedCount = 0;

        // У одного request_article может быть до 5 кандидатов (см.
        // halyk:search-sku, size=5) — матчим первого, кто проходит строгую
        // проверку по артикулу.
        $byArticle = $candidates->groupBy('request_article');

        foreach ($byArticle as $article => $group) {
            $articleNormalized = $this->normalizeArticle((string) $article);
            $pattern = '/(?<![A-Z0-9])' . preg_quote($articleNormalized, '/') . '(?![A-Z0-9])/u';

            $matchedId = null;
            foreach ($group as $row) {
                $nameNormalized = $this->normalizeArticle($row->halyk_name ?? '');
                if ($nameNormalized !== '' && preg_match($pattern, $nameNormalized)) {
                    $matchedId = $row->id;
                    break;
                }
            }

            if ($matchedId) {
                DB::table('halyk_sku_candidates')->where('id', $matchedId)->update([
                    'matched'    => true,
                    'updated_at' => now(),
                ]);
                $matchedCount++;
                $this->line("✓ {$article} — сопоставлен");
            } else {
                $this->line("⨯ {$article} — ни один кандидат не прошёл строгую проверку по артикулу");
            }
        }

        $this->info("Сопоставлено: {$matchedCount} из {$byArticle->count()}");
        return 0;
    }

    private function normalizeArticle(string $s): string
    {
        $s = mb_strtoupper($s);
        return str_replace('-', '', $s);
    }
}
