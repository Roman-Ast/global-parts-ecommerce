<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class BindKaspiProductsCommand extends Command
{
    protected $signature = 'kaspi:bind-products
                        {--delay=2000 : Задержка между запросами в мс}
                        {--limit=0 : Лимит (0 = все)}';

    protected $description = 'Привязывает нераспознанные товары Каспи к карточкам через link-to-master';

    private string $merchantCode = '30360429';

    private $cookies = 'mc-session=1783326645.435.114065.686134|825e5f3659dba1ed7b5d7b2cbf5f1012; mc-sid=0c990b19-7646-4c30-9369-4d97010aba96';

    public function handle(): int
    {
        
        $delay   = (int) $this->option('delay');
        $limit   = (int) $this->option('limit');

        if (empty($this->cookies)) {
            $this->error('Укажи куки: --cookies="mc-session=...; mc-sid=..."');
            return 1;
        }

        $query = DB::table('kaspi_feed_items')
            ->where('is_active', 1)
            ->where('bound', 0)
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $items = $query->get(['id', 'kaspi_sku', 'kaspi_name']);

        if ($items->isEmpty()) {
            $this->info('Нет товаров для привязки.');
            return 0;
        }

        $this->info("Начинаем привязку {$items->count()} товаров...");

        $success = 0;
        $failed  = 0;
        $expired = 0;
        $session_dead = false;

        foreach ($items as $item) {
            if ($session_dead) {
                $this->error('Обнови $this->cookies и запусти снова.');
                break;
            }

            $this->line("→ [{$item->kaspi_sku}] {$item->kaspi_name}");

            $result = $this->linkToMaster($item->kaspi_sku, $this->cookies);

            if ($result === null) {
                $this->error('Сессия протухла! Обнови куки и запусти снова.');
                $session_dead = true;
                break;
            }

            if ($result === true) {
                DB::table('kaspi_feed_items')
                    ->where('id', $item->id)
                    ->update(['bound' => 1]);
                $success++;
                $this->line("  ✓ Привязан");
            } elseif (is_string($result) && $this->isExpiredMasterError($result)) {
                // Мастер-карточка на стороне Kaspi мертва (EXPIRED) окончательно —
                // это не временное отсутствие в прайсе поставщика, а статус самого Kaspi.
                // Если она когда-нибудь появится заново, kaspi:match всё равно создаст/обновит
                // нужную строку через upsert по kaspi_sku — хранить мёртвую запись впрок смысла нет.
                DB::table('kaspi_feed_items')
                    ->where('id', $item->id)
                    ->delete();
                $expired++;
                $this->warn("  ⚠ Мастер-карточка EXPIRED — запись удалена из kaspi_feed_items");
            } else {
                $failed++;
                $this->warn("  ✗ Ошибка: {$result}");
            }

            usleep($delay * 1000);
        }

        $this->info("Готово. Привязано: {$success}, удалено (EXPIRED): {$expired}, ошибок: {$failed}");
        return 0;
    }

    private function isExpiredMasterError(string $result): bool
    {
        return str_contains($result, 'master.product.expired')
            || str_contains(strtolower($result), 'status expired');
    }

    private function linkToMaster(string $kaspiSku, string $cookies): true|null|string
    {
        try {
            $response = Http::withHeaders([
                'Accept'          => 'application/json, text/plain, */*',
                'Accept-Language' => 'ru,ru-RU;q=0.9,en-US;q=0.8,en;q=0.7',
                'Content-Type'    => 'application/json',
                'Origin'          => 'https://kaspi.kz',
                'Referer'         => 'https://kaspi.kz/',
                'User-Agent'      => 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Mobile Safari/537.36',
                'X-Auth-Version'  => '3',
                'Cookie'          => $cookies,
            ])->timeout(15)->post(
                'https://mc.shop.kaspi.kz/content/pending/mc/product/link-to-master?isMobileApp=false',
                [
                    'merchantProductCode' => $kaspiSku,
                    'masterProductCode'   => $kaspiSku,
                    'merchantCode'        => $this->merchantCode,
                ]
            );

            if ($response->status() === 401) {
                $this->error('401 ответ: ' . $response->body());
                return null;
            }

            if ($response->successful()) {
                return true;
            }

            return "HTTP {$response->status()}: " . $response->body();

        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
