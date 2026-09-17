<?php

namespace App\Console\Commands;

use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Восстанавливает сообщения, пропущенные во время простоя вебхука
 * (2026-09-16 вечер — 2026-09-17 день, комп был выключен) — вытащить их
 * через `receiveNotification` не удалось: известный баг Green API
 * (github.com/green-api/issues/issues/1115), очередь "на получение"
 * реально не пуста (счётчик 404 в консоли), но метод стабильно отдаёт
 * пустой ответ. Обходной путь — `getChatHistory` тянет историю НАПРЯМУЮ
 * из WhatsApp, не из очереди уведомлений (github.com/green-api/issues/
 * issues/1118 — ровно этот же класс бага, подтверждён живьём 2026-09-17).
 *
 * Чаты (`getChats`) отдаются отсортированными по свежести последнего
 * сообщения — идём сверху, тянем `getChatHistory` на каждый, и
 * останавливаемся на первом чате, где САМОЕ свежее сообщение уже старше
 * `--since` (дальше по списку будет только ещё старее). Дедуп —
 * `idMessage` уже есть в whatsapp_messages, пропускаем.
 *
 * Каждое восстановленное сообщение прогоняется через ТОТ ЖЕ
 * WhatsAppWebhookController::handle(), реконструируя вебхук-подобное
 * тело — та же логика (исключённые номера/группа "Темщики", парсинг
 * типов сообщений, upsert лида), что и у живого вебхука.
 */
class RecoverGreenApiHistoryCommand extends Command
{
    protected $signature = 'whatsapp:recover-history {--since=} {--source=} {--history-count=50} {--dry-run}';

    protected $description = 'Восстанавливает пропущенные сообщения через getChatHistory (обход бага receiveNotification)';

    public function handle(WhatsAppWebhookController $webhookController): int
    {
        $since = $this->option('since')
            ? \Carbon\Carbon::parse($this->option('since'))
            : now()->subHours(24);
        $sinceTs = $since->timestamp;
        $dryRun = (bool) $this->option('dry-run');
        $historyCount = (int) $this->option('history-count');

        $this->info("Восстанавливаю сообщения с {$since->format('Y-m-d H:i')} (unix {$sinceTs})" . ($dryRun ? ' [dry-run]' : ''));

        $onlySource = $this->option('source');
        $instances = config('services.green_api.instances', []);
        if ($onlySource) {
            $instances = array_intersect_key($instances, [$onlySource => true]);
        }

        foreach ($instances as $source => $creds) {
            $instanceId = $creds['instance_id'] ?? null;
            $token = $creds['token'] ?? null;
            if (!$instanceId || !$token) {
                $this->warn("[{$source}] нет instance_id/token — пропуск");
                continue;
            }

            $this->info("[{$source}] получаю список чатов...");
            $chatsResp = Http::timeout(30)->get("https://api.green-api.com/waInstance{$instanceId}/getChats/{$token}");
            if (!$chatsResp->successful()) {
                $this->error("[{$source}] getChats HTTP {$chatsResp->status()}");
                continue;
            }
            $chats = $chatsResp->json() ?? [];
            $this->info("[{$source}] всего чатов: " . count($chats) . ', иду сверху пока свежее ' . $since->format('H:i'));

            $recovered = 0;
            $chatsScanned = 0;

            foreach ($chats as $chat) {
                if (($chat['type'] ?? '') !== 'user') {
                    continue; // групповые чаты пропускаем целиком — лиды это личные номера, не группы
                }

                $chatsScanned++;
                $historyResp = $this->getChatHistoryWithRetry($instanceId, $token, $chat['id'], $historyCount);

                if (!$historyResp || !$historyResp->successful()) {
                    $this->error("[{$source}] getChatHistory({$chat['id']}) HTTP " . ($historyResp?->status() ?? 'нет ответа') . ' (после ретраев)');
                    continue;
                }

                usleep(400_000); // мягкий троттлинг — избегаем 429 на следующем чате

                $messages = $historyResp->json() ?? [];
                if (empty($messages)) {
                    continue;
                }

                // Список внутри чата тоже по убыванию времени — самое свежее первое.
                $newestTs = $messages[0]['timestamp'] ?? 0;
                if ($newestTs < $sinceTs) {
                    // Дальше по списку чатов будет только старее — стоп по инстансу.
                    $this->line("  [{$source}] чат '{$chat['name']}' старше отсечки — останавливаюсь ({$chatsScanned} чатов просмотрено)");
                    break;
                }

                foreach (array_reverse($messages) as $msg) {
                    $ts = $msg['timestamp'] ?? 0;
                    if ($ts < $sinceTs) {
                        continue;
                    }

                    $idMessage = $msg['idMessage'] ?? null;
                    if (!$idMessage) {
                        continue;
                    }

                    if (DB::table('whatsapp_messages')->where('message_id', $idMessage)->exists()) {
                        continue; // уже есть — либо долетело само, либо уже восстановлено
                    }

                    $body = $this->buildWebhookBody($msg, $chat, (int) $instanceId);
                    if (!$body) {
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("  [{$source}] БЫ восстановил: {$chat['name']} / {$idMessage} / " . ($msg['typeMessage'] ?? '?'));
                        $recovered++;
                        continue;
                    }

                    try {
                        $fakeRequest = Request::create('/whatsapp/webhook', 'POST', [], [], [], [], json_encode($body));
                        $fakeRequest->headers->set('Content-Type', 'application/json');
                        $webhookController->handle($fakeRequest);
                        $recovered++;
                    } catch (\Throwable $e) {
                        $this->error("  [{$source}] {$idMessage} упал: {$e->getMessage()}");
                    }
                }
            }

            $this->info("[{$source}] готово: {$recovered} сообщений восстановлено, {$chatsScanned} чатов просмотрено.");
        }

        return 0;
    }

    /**
     * getChatHistory ловит частые HTTP 429 при последовательном обходе многих
     * чатов подряд (живьём подтверждено — примерно каждый второй чат). Без
     * ретрая такой чат молча выпадает из восстановления. Три попытки с
     * нарастающей паузой (2с/5с/10с) — этого хватило на живом тесте.
     */
    private function getChatHistoryWithRetry(string $instanceId, string $token, string $chatId, int $count)
    {
        $delays = [2, 5, 10];
        $response = null;

        for ($attempt = 0; $attempt <= count($delays); $attempt++) {
            $response = Http::timeout(20)->post("https://api.green-api.com/waInstance{$instanceId}/getChatHistory/{$token}", [
                'chatId' => $chatId,
                'count'  => $count,
            ]);

            if ($response->successful() || $response->status() !== 429) {
                return $response;
            }

            if ($attempt < count($delays)) {
                sleep($delays[$attempt]);
            }
        }

        return $response;
    }

    /**
     * Реконструирует тело вебхука из одной записи getChatHistory — тот же
     * формат, что реально присылает Green API живьём, чтобы прогнать через
     * WhatsAppWebhookController::handle() без дублирования его логики.
     */
    private function buildWebhookBody(array $msg, array $chat, int $instanceId): ?array
    {
        $isIncoming = ($msg['type'] ?? '') === 'incoming';
        $typeMessage = $msg['typeMessage'] ?? 'chat';

        $messageData = ['typeMessage' => $typeMessage];

        switch ($typeMessage) {
            case 'textMessage':
                $messageData['textMessageData'] = ['textMessage' => $msg['textMessage'] ?? ''];
                break;
            case 'extendedTextMessage':
                $messageData['extendedTextMessageData'] = ['text' => $msg['extendedTextMessage']['text'] ?? ($msg['textMessage'] ?? '')];
                break;
            case 'imageMessage':
                $messageData['imageMessageData'] = [
                    'downloadUrl' => $msg['downloadUrl'] ?? null,
                    'caption'     => $msg['caption'] ?? null,
                ];
                break;
            case 'documentMessage':
                $messageData['documentMessageData'] = [
                    'downloadUrl' => $msg['downloadUrl'] ?? null,
                    'fileName'    => $msg['fileName'] ?? 'Документ',
                    'caption'     => $msg['caption'] ?? null,
                ];
                break;
            case 'audioMessage':
                $messageData['audioMessageData'] = ['downloadUrl' => $msg['downloadUrl'] ?? null];
                break;
            case 'videoMessage':
                $messageData['videoMessageData'] = ['downloadUrl' => $msg['downloadUrl'] ?? null];
                break;
            default:
                // Неизвестный/редкий тип — не теряем сообщение целиком,
                // просто без текста (как и в handle() для необработанных типов).
                break;
        }

        return [
            'typeWebhook'  => $isIncoming ? 'incomingMessageReceived' : 'outgoingMessageReceived',
            'idMessage'    => $msg['idMessage'],
            'instanceData' => ['idInstance' => $instanceId],
            'senderData'   => [
                'chatId'     => $chat['id'],
                'chatName'   => $chat['name'] ?? null,
                'sender'     => $msg['senderId'] ?? $chat['id'],
                'senderName' => $msg['senderName'] ?? $chat['name'] ?? null,
            ],
            'messageData'  => $messageData,
        ];
    }
}
