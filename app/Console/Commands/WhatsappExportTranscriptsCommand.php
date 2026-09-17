<?php

namespace App\Console\Commands;

use App\Models\WhatsappLead;
use Illuminate\Console\Command;

/**
 * Экспорт сырых переписок в читаемый текст — под ручной разбор (вставить в
 * claude.ai самому, или разобрать вместе со мной), см. решение Романа
 * 2026-09-14: неделю копим сырьё бесплатно (WHATSAPP_LLM_EXTRACTION_ENABLED
 * выключен), потом вручную сверяем с реальным исходом каждого диалога и
 * выводим фиксированный список причин отказа (~5 штук), прежде чем включать
 * платный LLM-слой — чтобы не получить "1000 причин" от свободной генерации.
 */
class WhatsappExportTranscriptsCommand extends Command
{
    protected $signature = 'whatsapp:export-transcripts {--days=7} {--output=}';

    protected $description = 'Экспортирует переписки WhatsApp за N дней в читаемый текстовый файл';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $since = now()->subDays($days);

        $leads = WhatsappLead::with(['messages' => fn ($q) => $q->orderBy('created_at')])
            ->where('last_seen_at', '>=', $since)
            ->orderBy('last_seen_at')
            ->get();

        if ($leads->isEmpty()) {
            $this->info('Нет переписок за этот период.');
            return 0;
        }

        $output = $this->option('output') ?: storage_path('app/whatsapp_export_' . now()->format('Y-m-d_His') . '.txt');

        $lines = [];
        $lines[] = "Экспорт переписок WhatsApp — {$leads->count()} лидов, период: последние {$days} дн. (с " . $since->format('Y-m-d') . ')';
        $lines[] = str_repeat('=', 80);

        foreach ($leads as $lead) {
            $lines[] = '';
            $lines[] = str_repeat('-', 80);
            $lines[] = "ЛИД #{$lead->id} | тел: +{$lead->phone} | источник: " . ($lead->source ?? '?')
                . ' | статус: ' . ($lead->status ?? '?')
                . ' | сообщений: ' . $lead->messages->count();
            $lines[] = str_repeat('-', 80);

            foreach ($lead->messages as $msg) {
                $who = $msg->is_incoming ? 'КЛИЕНТ' : 'МЫ';
                $when = $msg->created_at->format('Y-m-d H:i');
                $text = $msg->message_text ?: ($msg->file_url ? '[вложение: ' . $msg->file_url . ']' : '[пусто]');
                $lines[] = "[{$when}] {$who}: {$text}";
            }
        }

        file_put_contents($output, implode("\n", $lines));

        $this->info("Готово: {$leads->count()} лидов, файл: {$output}");
        return 0;
    }
}
