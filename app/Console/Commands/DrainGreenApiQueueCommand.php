<?php

namespace App\Console\Commands;

use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Разово вычитывает очередь непереданных вебхуков Green API — накопилась,
 * пока локальный сервер (куда сейчас смотрит вебхук) был выключен
 * (Роман, 2026-09-17). Green API сам складывает недоставленные уведомления
 * в очередь на своей стороне (не теряет их), есть отдельный API на выборку:
 * `GET .../receiveNotification/...` отдаёт СТРОГО ОДНО уведомление за раз
 * (`{"receiptId":N,"body":{...}}`, `body` — 1-в-1 то же тело, что обычно
 * прилетает POST'ом на вебхук), `DELETE .../deleteNotification/.../{receiptId}`
 * подтверждает получение и убирает его из очереди — без удаления Green API
 * будет отдавать то же самое уведомление снова и снова. Гоняем цикл, пока
 * очередь не опустеет (пустой ответ/null).
 *
 * Каждое уведомление прогоняется через ТОТ ЖЕ WhatsAppWebhookController::
 * handle(), что и обычный вебхук — синтетический Request с тем же телом,
 * никакой отдельной логики сохранения не пишем, чтобы не разойтись с
 * боевым путём (дедуп по message_id там уже встроен через updateOrCreate,
 * так что повторный прогон одного и того же уведомления не создаст дублей).
 */
class DrainGreenApiQueueCommand extends Command
{
    protected $signature = 'whatsapp:drain-queue {--source=} {--limit=1000}';

    protected $description = 'Вычитывает накопившуюся очередь недоставленных вебхуков Green API и сохраняет их как обычно';

    public function handle(WhatsAppWebhookController $webhookController): int
    {
        $onlySource = $this->option('source');
        $limit = (int) $this->option('limit');

        $instances = config('services.green_api.instances', []);
        if ($onlySource) {
            $instances = array_intersect_key($instances, [$onlySource => true]);
        }

        foreach ($instances as $source => $creds) {
            $instanceId = $creds['instance_id'] ?? null;
            $token = $creds['token'] ?? null;

            if (!$instanceId || !$token) {
                $this->warn("[{$source}] нет instance_id/token в .env — пропуск");
                continue;
            }

            $this->info("[{$source}] вычитываю очередь (instance {$instanceId})...");
            $count = 0;

            while ($count < $limit) {
                $response = Http::timeout(15)
                    ->get("https://api.green-api.com/waInstance{$instanceId}/receiveNotification/{$token}");

                if (!$response->successful()) {
                    $this->error("[{$source}] receiveNotification HTTP {$response->status()} — {$response->body()}");
                    break;
                }

                $notification = $response->json();
                if (empty($notification) || empty($notification['body'])) {
                    // Очередь пуста — Green API отдаёт null/пустое тело.
                    break;
                }

                $receiptId = $notification['receiptId'];
                $body = $notification['body'];

                try {
                    $fakeRequest = Request::create('/whatsapp/webhook', 'POST', [], [], [], [], json_encode($body));
                    $fakeRequest->headers->set('Content-Type', 'application/json');
                    $webhookController->handle($fakeRequest);
                } catch (\Throwable $e) {
                    $this->error("[{$source}] receiptId={$receiptId} обработка упала: {$e->getMessage()}");
                    // Всё равно подтверждаем получение — иначе застрянем на этом
                    // же уведомлении навсегда. Ошибка уже залогирована выше.
                }

                Http::timeout(15)
                    ->delete("https://api.green-api.com/waInstance{$instanceId}/deleteNotification/{$token}/{$receiptId}");

                $count++;
                $this->line("  [{$source}] receiptId={$receiptId} " . ($body['typeWebhook'] ?? '?') . " — обработано и подтверждено");
            }

            $this->info("[{$source}] готово: {$count} уведомлений вычитано.");
        }

        return 0;
    }
}
