<?php

namespace App\Console\Commands;

use App\Models\WhatsappMessage;
use App\Services\ClaudeExtractionService;
use App\Services\LeadRequestExtractor;
use App\Support\LeadStatuses;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Разовый бэкфилл накопленной истории (просьба Романа 2026-10-03, ~700
 * лидов накопились, пока WHATSAPP_LLM_EXTRACTION_ENABLED весь месяц был
 * выключен — lead_requests/demand_signals сейчас пустые). Проходит по ВСЕМ
 * ещё необработанным входящим сообщениям (llm_processed_at IS NULL) в
 * хронологическом порядке через тот же LeadRequestExtractor, что и живой
 * WhatsappMessageObserver — заполняет lead_requests. Анализ исходов
 * (demand_signals) по умолчанию НЕ запускается автоматически — см.
 * --with-analyze ниже, живой 504 на проде 2026-10-03 показал, что
 * извлечение+анализ за один HTTP-запрос слишком легко вылетает за таймаут
 * nginx даже на небольших --leads.
 *
 * --dry-run не делает НИ ОДНОГО обращения к Claude — только считает объём
 * (сколько сообщений, из них с вложением), чтобы прикинуть реальный масштаб
 * ДО того как платить. Точную стоимость в долларах эта команда не считает
 * (у нас нет надёжного способа предсказать токены без реального вызова) —
 * свериться с текущими тарифами модели (Sonnet) на странице цен Anthropic самому.
 */
class BackfillDemandDataCommand extends Command
{
    protected $signature = 'whatsapp:backfill-demand {--dry-run} {--limit=} {--leads= : Ограничить N САМЫМИ СТАРЫМИ лидами (не сообщениями) вместо --limit} {--with-analyze : Сразу же прогнать whatsapp:analyze-demand в конце (по умолчанию выключено)}';

    protected $description = 'Разовый бэкфилл накопленной истории WhatsApp в lead_requests/demand_signals';

    public function handle(LeadRequestExtractor $extractor): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $leadsLimit = $this->option('leads') ? (int) $this->option('leads') : null;

        $baseQuery = fn () => WhatsappMessage::query()
            ->where('is_incoming', true)
            ->whereNull('llm_processed_at')
            ->whereHas('lead', fn ($q) => $q->where('status', '!=', LeadStatuses::STAFF_STATUS));

        if ($leadsLimit) {
            // Берём N самых старых лидов (по первому необработанному сообщению),
            // а не первые N сообщений — иначе один активный чат с длинной
            // историей мог бы съесть весь --limit на себя одного, и реальных
            // РАЗНЫХ лидов для контрольного прогона увидели бы единицы.
            $leadIds = $baseQuery()
                ->orderBy('created_at')
                ->pluck('whatsapp_lead_id')
                ->unique()
                ->take($leadsLimit)
                ->values();

            $query = $baseQuery()->whereIn('whatsapp_lead_id', $leadIds)->orderBy('created_at');
        } else {
            $query = $baseQuery()->orderBy('created_at');
            if ($limit) {
                $query->limit($limit);
            }
        }

        $messages = $query->get();

        if ($messages->isEmpty()) {
            $this->info('Нечего обрабатывать — все входящие сообщения уже разобраны.');
            return 0;
        }

        $withAttachment = $messages->filter(fn ($m) => !empty($m->file_url));
        $textOnly = $messages->filter(fn ($m) => empty($m->file_url) && !empty($m->message_text));
        $leadsAffected = $messages->pluck('whatsapp_lead_id')->unique()->count();
        $totalChars = $textOnly->sum(fn ($m) => mb_strlen((string) $m->message_text));

        $this->info("Сообщений к разбору: {$messages->count()} (лидов: {$leadsAffected})");
        $this->line("  из них с вложением (фото/PDF — отдельный vision-запрос каждое): {$withAttachment->count()}");
        $this->line("  из них только текст: {$textOnly->count()}, суммарно ~{$totalChars} символов");

        if ($dryRun) {
            $this->warn('--dry-run: ни одного обращения к Claude не сделано. Сверь объём с текущими тарифами модели (см. MODEL в ClaudeExtractionService) на anthropic.com/pricing перед реальным запуском.');
            return 0;
        }

        ClaudeExtractionService::resetUsageTotals();

        $this->info('Разбираю сообщения через Claude...');
        $bar = $this->output->createProgressBar($messages->count());
        $bar->start();

        foreach ($messages as $message) {
            $extractor->extract($message);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // По умолчанию ВЫКЛЮЧЕНО (просьба Романа 2026-10-03) — на shared-
        // хостинге без SSH любая команда идёт через обычный веб-запрос с
        // таймаутом nginx (~60 сек); извлечение + сразу следом анализ исхода
        // за ОДИН HTTP-запрос легко удваивает время и ловит 504, даже когда
        // --leads достаточно маленький, чтобы каждый шаг по отдельности
        // укладывался. Теперь по умолчанию бэкфилл делает только извлечение
        // (lead_requests), а анализ исходов (demand_signals) — отдельный
        // прогон whatsapp:analyze-demand --limit=N, который уже проверен
        // живьём на таких порциях.
        if ($this->option('with-analyze')) {
            $this->info('Готово с извлечением. Запускаю анализ исходов (whatsapp:analyze-demand --all)...');
            Artisan::call('whatsapp:analyze-demand', ['--all' => true, '--limit' => 100000]);
            $this->line(trim(Artisan::output()));
        } else {
            $this->info('Готово с извлечением. Анализ исходов — отдельным шагом: whatsapp:analyze-demand --limit=N');
        }

        $usage = ClaudeExtractionService::getUsageTotals();
        $this->newLine();
        $this->info('--- Расход токенов за прогон (' . ($this->option('with-analyze') ? 'извлечение + анализ исходов вместе' : 'только извлечение') . ') ---');
        $this->line("Вызовов Claude: {$usage['calls']}");
        $this->line("Input токенов: {$usage['input_tokens']}");
        $this->line("Output токенов: {$usage['output_tokens']}");
        $this->warn('Точная цена зависит от текущего тарифа модели (Sonnet) за млн токенов — свериться на anthropic.com/pricing и посчитать от этих цифр (вход/выход считаются по-разному, не путать).');

        return 0;
    }
}
