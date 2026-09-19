<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WhatsappLead;
use App\Models\WhatsappMessage;
use App\Support\LeadStatuses;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Личные номера Романа и коллег + рабочая группа "Темщики" — переписка
     * оттуда НЕ должна попадать в whatsapp_leads/whatsapp_messages вообще
     * (по прямому указанию Романа 2026-09-14): это не клиенты, а мы сами,
     * и засоряет сырую таблицу, которую он раз в неделю разбирает вручную.
     * Числа — без "+"/"@c.us", как приходят от Green API в senderData.
     */
    const EXCLUDED_PHONES = ['77078508810', '77073707527', '77476204450'];

    /** Регистронезависимо, по senderData.chatName групповых сообщений (@g.us). */
    const EXCLUDED_GROUP_NAMES = ['темщики'];

    public function handle(Request $request)
    {
        $data = $request->all();

        if (empty($data)) {
            return response()->json(['status' => 'empty_payload']);
        }

        // Игнорируем системные уведомления (типа quotaExceeded), чтобы не мусорить
        $typeWebhook = $data['typeWebhook'] ?? '';
        if (!in_array($typeWebhook, ['incomingMessageReceived', 'outgoingMessageReceived', 'outgoingAPIMessageReceived', 'outgoingMessageStatus'])) {
            return response()->json(['status' => 'ignored_system_webhook']);
        }

        Log::info('Webhook received', ['payload' => $data]);

        // Статус исходящего сообщения (sent/delivered/read/failed) — отдельная форма
        // пейлоада, без messageData/senderData, апдейт статуса, а не создание строки.
        // Галочки в chatCode == is_read у нас про ВХОДЯЩИЕ (прочитали ли МЫ), это же —
        // прочитал ли клиент НАШЕ сообщение, для сине-серых галочек в UI.
        if ($typeWebhook === 'outgoingMessageStatus') {
            $idMessage = $data['idMessage'] ?? null;
            $status = $data['status'] ?? null;

            if ($idMessage && $status) {
                WhatsappMessage::where('message_id', $idMessage)->update(['status' => $status]);
            }

            return response()->json(['status' => 'success']);
        }

        try {
            $chatIdRaw = $data['chatId'] 
                    ?? ($data['senderData']['chatId'] ?? ($data['instanceData']['wid'] ?? null));

            if (!$chatIdRaw) {
                return response()->json(['status' => 'no_id_found']);
            }

            $phone = str_replace('@c.us', '', $chatIdRaw);
            $instanceId = (string)($data['instanceData']['idInstance'] ?? 'unknown');

            // Личные/рабочие номера и группа "Темщики" — не клиенты, в БД не пишем
            // вообще (см. EXCLUDED_PHONES/EXCLUDED_GROUP_NAMES выше). Группа сама
            // идёт под chatId вида "...@g.us" — реальный автор сообщения внутри
            // неё лежит отдельно, в senderData.sender.
            $groupChatName = $data['senderData']['chatName'] ?? null;
            $groupSenderPhone = isset($data['senderData']['sender'])
                ? str_replace('@c.us', '', $data['senderData']['sender'])
                : null;

            $isExcluded = in_array($phone, self::EXCLUDED_PHONES, true)
                || ($groupSenderPhone && in_array($groupSenderPhone, self::EXCLUDED_PHONES, true))
                || ($groupChatName && in_array(mb_strtolower(trim($groupChatName)), self::EXCLUDED_GROUP_NAMES, true));

            if ($isExcluded) {
                return response()->json(['status' => 'excluded_internal_chat']);
            }

            // "Рабочие" (просьба Романа 2026-09-19) — в отличие от
            // EXCLUDED_PHONES выше (исключены ДО появления лида, зашито в
            // коде), это лид, УЖЕ существующий в whatsapp_leads, который
            // Роман постфактум вручную перенёс в этот статус, поняв, что
            // это свой/коллега/поставщик, а не клиент. Дальнейшие
            // сообщения по этому номеру больше не сохраняем совсем (ни
            // входящие, ни исходящие) — тем же ранним return'ом, что и у
            // исключённых номеров, ничего в whatsapp_leads/whatsapp_messages
            // не трогаем. Уже сохранённая до переноса история не удаляется.
            $existingLead = WhatsappLead::where('phone', $phone)->first();
            if ($existingLead && $existingLead->status === LeadStatuses::STAFF_STATUS) {
                return response()->json(['status' => 'muted_staff_contact']);
            }

            // instanceId => source ('site'/'2gis'/...) — см. config/services.php
            // green_api.instance_sources. Незнакомый инстанс (ещё не вписанный
            // в конфиг) падает в 'unknown', а не молча приписывается 2gis.
            $source = config("services.green_api.instance_sources.{$instanceId}", 'unknown');

            $lead = WhatsappLead::updateOrCreate(
                ['phone' => $phone],
                [
                    'last_seen_at' => now(),
                    'source' => $source,
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
            // Ответ (reply) на конкретное сообщение — реальный НОВЫЙ текст лежит в
            // extendedTextMessageData.text, а quotedMessage.* это данные исходного
            // (процитированного) сообщения, не то, что написал клиент сейчас.
            // Найдено живьём 2026-09-15 при разборе сырых данных: без этой ветки
            // весь текст ответа терялся, сохранялось пустое сообщение — 23 из 386
            // сообщений на тот момент (~6%) были именно такими "немыми" ответами.
            elseif ($typeMessage === 'quotedMessage') {
                $text = $messageData['extendedTextMessageData']['text'] ?? '';
                $quotedType = $messageData['quotedMessage']['typeMessage'] ?? null;
                if ($quotedType === 'imageMessage') {
                    $text = "[Ответ на фото] {$text}";
                } elseif ($quotedType === 'documentMessage') {
                    $text = "[Ответ на файл] {$text}";
                }
            }
            // Реакция эмодзи на сообщение — сам эмодзи лежит в extendedTextMessageData.text
            elseif ($typeMessage === 'reactionMessage') {
                $emoji = $messageData['extendedTextMessageData']['text'] ?? '';
                $text = $emoji !== '' ? "[Реакция: {$emoji}]" : '[Реакция]';
            }
            // Визитка контакта — вытаскиваем хотя бы отображаемое имя
            elseif ($typeMessage === 'contactMessage') {
                $contactName = $messageData['contactMessageData']['displayName'] ?? null;
                $text = $contactName ? "[Контакт] {$contactName}" : '[Контакт]';
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