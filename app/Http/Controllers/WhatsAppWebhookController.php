<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WhatsappLead;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $data = $request->all();

        if (empty($data)) {
            return response()->json(['status' => 'empty_payload']);
        }

        // Игнорируем системные уведомления (типа quotaExceeded), чтобы не мусорить
        $typeWebhook = $data['typeWebhook'] ?? '';
        if (!in_array($typeWebhook, ['incomingMessageReceived', 'outgoingMessageReceived', 'outgoingAPIMessageReceived'])) {
            return response()->json(['status' => 'ignored_system_webhook']);
        }

        Log::info('Webhook received', ['payload' => $data]);

        try {
            $chatIdRaw = $data['chatId'] 
                    ?? ($data['senderData']['chatId'] ?? ($data['instanceData']['wid'] ?? null));

            if (!$chatIdRaw) {
                return response()->json(['status' => 'no_id_found']);
            }

            $phone = str_replace('@c.us', '', $chatIdRaw);
            $instanceId = (string)($data['instanceData']['idInstance'] ?? 'unknown');

            $lead = WhatsappLead::updateOrCreate(
                ['phone' => $phone],
                [
                    'last_seen_at' => now(),
                    'source' => ($instanceId === '7107585549') ? 'site' : '2gis',
                    'client_name' => $data['senderData']['senderName'] ?? null
                ]
            );

            // --- ЛОГИКА ДЛЯ ТЕКСТА И ФАЙЛОВ (ИСПРАВЛЕННАЯ) ---
            $messageData = $data['messageData'] ?? [];
            $typeMessage = $messageData['typeMessage'] ?? 'chat';
            $text = '';
            $fileUrl = null;

            if ($typeMessage === 'textMessage') {
                $text = $messageData['textMessageData']['textMessage'] ?? '';
            } 
            elseif ($typeMessage === 'extendedTextMessage') {
                $text = $messageData['extendedTextMessageData']['text'] ?? '';
            } 
            // Обработка картинок
            elseif ($typeMessage === 'imageMessage') {
                $fileUrl = $messageData['imageMessageData']['downloadUrl'] ?? null;
                $caption = $messageData['imageMessageData']['caption'] ?? null;
                $text = $caption ? "Фото: $caption" : "Изображение";
            }
            // Обработка документов (PDF и прочее)
            elseif ($typeMessage === 'documentMessage') {
                $fileUrl = $messageData['documentMessageData']['downloadUrl'] ?? null;
                $fileName = $messageData['documentMessageData']['fileName'] ?? 'Документ';
                $caption = $messageData['documentMessageData']['caption'] ?? null;
                $text = $caption ? "Файл ($fileName): $caption" : "Файл: $fileName";
            }
            // Обработка аудио/голосовых
            elseif ($typeMessage === 'audioMessage') {
                $fileUrl = $messageData['audioMessageData']['downloadUrl'] ?? null;
                $text = 'Голосовое сообщение';
            }
            // Обработка видео
            elseif ($typeMessage === 'videoMessage') {
                $fileUrl = $messageData['videoMessageData']['downloadUrl'] ?? null;
                $text = 'Видео файл';
            }

            // Если это неизвестный файл, но ссылка есть (на всякий случай)
            if (!$fileUrl && isset($messageData['fileMessageData']['downloadUrl'])) {
                $fileUrl = $messageData['fileMessageData']['downloadUrl'];
            }

            $lead->messages()->updateOrCreate(
                ['message_id' => $data['idMessage'] ?? uniqid('api_', true)],
                [
                    'instance_id' => $instanceId,
                    'message_text' => $text,
                    'file_url' => $fileUrl, // Теперь ссылка будет сохраняться
                    'is_incoming' => ($data['typeWebhook'] ?? '') === 'incomingMessageReceived',
                    'type' => $typeMessage,
                    'raw_body' => $data
                ]
            );

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('WEBHOOK CRASH: ' . $e->getMessage());
            return response()->json(['status' => 'error'], 500);
        }
    
    }

    private function extractVin($lead, $text)
    {
        $clean = strtoupper(str_replace([' ', '-', '_'], '', $text));
        // Ищем 17 символов (VIN)
        if (preg_match('/[A-HJ-NPR-Z0-9]{17}/', $clean, $matches)) {
            $vin = $matches[0];
            if ($lead->last_vin !== $vin) {
                $lead->update(['last_vin' => $vin]);
            }
        }
    }
}