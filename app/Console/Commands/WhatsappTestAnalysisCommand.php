<?php

namespace App\Console\Commands;

use App\Models\WhatsappLead;
use App\Services\ClaudeExtractionService;
use Illuminate\Console\Command;

/**
 * Чисто тестовый прогон (по просьбе Романа 2026-09-15) — "хочу посмотреть,
 * что выдаст LLM, но ещё рано делать боевой анализ". НИЧЕГО не пишет в БД
 * (ни lead_requests, ни demand_signals) — просто разбирает несколько
 * реальных диалогов и печатает результат в консоль. Стоит копейки (пара
 * запросов к Claude), можно гонять сколько угодно раз без последствий.
 *
 * Как только устроит качество и накопится нужный объём — тогда уже
 * whatsapp:analyze-demand (боевая команда, пишет в БД).
 */
class WhatsappTestAnalysisCommand extends Command
{
    protected $signature = 'whatsapp:test-analysis {--limit=3} {--lead=*}';

    protected $description = 'Тестовый прогон LLM-разбора на нескольких реальных диалогах, без записи в БД';

    public function handle(ClaudeExtractionService $claude): int
    {
        $leadIds = $this->option('lead');

        if (!empty($leadIds)) {
            $leads = WhatsappLead::whereIn('id', $leadIds)->get();
        } else {
            // По умолчанию — самые содержательные диалоги (больше всего сообщений),
            // там выше шанс увидеть реальный вопрос-ответ-исход, а не одно "здравствуйте".
            $leads = WhatsappLead::withCount('messages')
                ->orderByDesc('messages_count')
                ->limit((int) $this->option('limit'))
                ->get();
        }

        if ($leads->isEmpty()) {
            $this->info('Нет лидов для теста.');
            return 0;
        }

        foreach ($leads as $lead) {
            $messages = $lead->messages()->orderBy('created_at')->get();
            if ($messages->isEmpty()) {
                continue;
            }

            $this->line(str_repeat('=', 80));
            $this->info("Лид #{$lead->id} | +{$lead->phone} | источник: {$lead->source} | сообщений: {$messages->count()}");
            $this->line(str_repeat('=', 80));

            // parseRequest ожидает ОДНО клиентское сообщение — склеиваем весь
            // входящий текст клиента в один блок, это ближе к его формату,
            // чем весь двусторонний диалог целиком.
            $clientText = $messages->where('is_incoming', true)
                ->pluck('message_text')
                ->filter()
                ->implode("\n");

            if (trim($clientText) === '') {
                $this->warn('  Нет текста от клиента — пропуск.');
                continue;
            }

            $parsed = $claude->parseRequest($clientText);

            if (!$parsed) {
                $this->warn('  Claude не вернул результат по parseRequest (см. лог).');
                continue;
            }

            $this->line('--- Извлечено из текста клиента ---');
            $this->line('  VIN: ' . ($parsed['vin'] ?? '—'));
            $this->line('  Марка/модель: ' . ($parsed['brand'] ?? '—') . ' ' . ($parsed['car_model'] ?? '—') . ' (' . ($parsed['car_year'] ?? '—') . ')');
            $this->line('  Запчасти: ' . (empty($parsed['parts']) ? '—' : json_encode($parsed['parts'], JSON_UNESCAPED_UNICODE)));

            if (empty($parsed['parts'])) {
                $this->warn('  Нет распознанных запчастей — analyzeOutcome пропускаем.');
                continue;
            }

            $transcript = $messages->map(function ($m) {
                $who = $m->is_incoming ? 'Клиент' : 'Мы';
                return "[{$m->created_at->format('Y-m-d H:i')}] {$who}: " . ($m->message_text ?: '[вложение]');
            })->implode("\n");

            $outcome = $claude->analyzeOutcome($parsed['parts'], $transcript, now()->toIso8601String());

            if (!$outcome || empty($outcome['parts'])) {
                $this->warn('  Claude не вернул результат по analyzeOutcome (см. лог).');
                continue;
            }

            $this->line('--- Вердикт по диалогу целиком ---');
            foreach ($outcome['parts'] as $part) {
                $this->line("  • {$part['name']} ({$part['side']}/{$part['position']})");
                $this->line("    наличие: {$part['availability_answer']}" . ($part['lead_time_days'] ? ", срок {$part['lead_time_days']}дн" : '') . ($part['quoted_price'] ? ", цена {$part['quoted_price']}" : ''));
                $this->line("    исход: {$part['outcome']}" . ($part['decline_reason'] ? " ({$part['decline_reason']})" : ''));
                $this->line("    комментарий: {$part['notes']}");
            }
            $this->line('');
        }

        $this->info('Готово. Ничего не сохранено в БД — это только предпросмотр.');
        return 0;
    }
}
