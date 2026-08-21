<?php

namespace App\Console\Commands;

use App\Services\HalykMarketClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Привязывает сопоставленные (halyk:match) позиции к нашим карточкам на
 * Halyk Market: PUT .../save-and-map-sku. Требует HALYK_CITY_CODE и
 * HALYK_POINT_CODE в .env — коды города/точки продаж берутся в кабинете
 * Halyk Market, у нас их нет заранее, в отличие от Kaspi.
 *
 * Важно (см. CLAUDE.md): привязанный товар обязательно должен попадать в
 * КАЖДУЮ последующую выгрузку фида — иначе Halyk сам уводит его в архив.
 * Команда фида (halyk:generate-xml) ещё не написана — это следующий шаг
 * после того, как привязка на пилоте подтвердится рабочей.
 */
class HalykBindCommand extends Command
{
    protected $signature = 'halyk:bind {--limit=100}';

    protected $description = 'Привязывает сопоставленные позиции halyk_sku_candidates к карточкам на Halyk Market';

    public function handle(HalykMarketClient $client): int
    {
        $cityCode  = env('HALYK_CITY_CODE');
        $pointCode = env('HALYK_POINT_CODE');

        if (!$cityCode || !$pointCode) {
            $this->error('HALYK_CITY_CODE / HALYK_POINT_CODE не заданы в .env — узнай коды города и точки продаж в кабинете Halyk Market (partners.halykmarket.com) перед привязкой.');
            return 1;
        }

        $limit = (int) $this->option('limit');

        $rows = DB::table('halyk_sku_candidates as hc')
            ->join('kaspi_initial_products as kip', 'kip.sku', '=', 'hc.request_article')
            ->where('hc.matched', true)
            ->where('hc.bound', false)
            ->limit($limit)
            ->get(['hc.id', 'hc.request_article', 'hc.halyk_sku_id', 'kip.price', 'kip.stock']);

        if ($rows->isEmpty()) {
            $this->info('Нечего привязывать — нет сопоставленных и ещё не привязанных позиций.');
            return 0;
        }

        $this->info("Привязываем {$rows->count()} позиций...");

        foreach ($rows as $row) {
            $result = $client->bindSku([
                'skuId'               => $row->halyk_sku_id,
                'merchantProductCode' => $row->request_article,
                'city'                => ['code' => $cityCode],
                'price'               => (int) $row->price,
                'points'              => [[
                    'code'   => $pointCode,
                    'amount' => (int) $row->stock,
                ]],
                // 3 — минимальный поддерживаемый срок рассрочки (допустимо
                // только 3/6/12/24); сам факт наличия рассрочки не
                // обсуждали отдельно, взял минимум как безопасный дефолт.
                'loanPeriod'          => 3,
            ]);

            if ($result['ok']) {
                DB::table('halyk_sku_candidates')->where('id', $row->id)->update([
                    'bound'      => true,
                    'bound_at'   => now(),
                    'updated_at' => now(),
                ]);
                $this->line("✓ {$row->request_article} — привязан");
            } else {
                $this->error("⨯ {$row->request_article} — HTTP {$result['status']}: " . json_encode($result['body'], JSON_UNESCAPED_UNICODE));
            }

            usleep(300000);
        }

        return 0;
    }
}
