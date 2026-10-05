<?php

namespace App\Observers;

use App\Jobs\ExtractLeadRequestJob;
use App\Models\WhatsappLead;
use App\Models\WhatsappMessage;
use App\Support\LeadStatuses;

class WhatsappMessageObserver
{
    public function created(WhatsappMessage $message)
    {
        if (!$message->is_incoming) return;

        // Рубильник по решению Романа 2026-09-14 — сырые сообщения копим всегда
        // (это просто INSERT из вебхука, бесплатно), а платный LLM-разбор стоит
        // выключенным, пока таксономия причин отказа не выведена вручную из
        // реальных чатов за первую неделю. См. .env WHATSAPP_LLM_EXTRACTION_ENABLED.
        if (!config('services.anthropic.whatsapp_extraction_enabled')) {
            return;
        }

        // "Рабочие" (просьба Романа 2026-09-21, исправляет более раннее
        // противоречивое решение того же дня — см. CLAUDE.md/WhatsAppWebhookController):
        // свои/коллеги/рабочая группа, перенесённые в этот статус, ДОЛЖНЫ
        // сохраняться в whatsapp_messages как обычно (иначе колонку "Рабочие"
        // просто нечем наполнить), но НИКОГДА не должны попадать в платный
        // LLM-разбор спроса и в lead_requests/demand_signals — это не
        // клиентские запросы, анализировать их как спрос бессмысленно и
        // просто тратит деньги на Claude API впустую.
        $leadStatus = WhatsappLead::where('id', $message->whatsapp_lead_id)->value('status');
        if ($leadStatus === LeadStatuses::STAFF_STATUS) {
            return;
        }

        // Сама логика разбора — в LeadRequestExtractor (вынесено 2026-10-03,
        // чтобы её же использовал батч-бэкфилл по накопленной истории,
        // whatsapp:backfill-demand — одна и та же логика что для новых
        // сообщений на лету, что для старых задним числом). Через очередь
        // (2026-10-05, см. ExtractLeadRequestJob) — НЕ вызываем синхронно
        // прямо здесь, иначе обработка вебхука ждёт ответ Claude.
        ExtractLeadRequestJob::dispatch($message->id);
    }
}
