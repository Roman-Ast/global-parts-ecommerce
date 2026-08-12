<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunKaspiPipeline extends Command
{
    protected $signature = 'kaspi:pipeline';
    protected $description = 'Полный прогон: prices:fetch -> kaspi:reprice -> kaspi:generate-xml';

    protected array $steps = [
        'prices:fetch',
        'kaspi:reprice',
        'kaspi:generate-xml',
    ];

    public function handle()
    {
        $this->info('=== СТАРТ ПОЛНОГО ПРОГОНА ===');
        $this->info('Начало: ' . now()->format('Y-m-d H:i:s'));
        $this->newLine();

        foreach ($this->steps as $i => $step) {
            $num = $i + 1;
            $total = count($this->steps);
            $this->info("[$num/$total] Запускаю: $step ...");

            $start = microtime(true);

            try {
                $exitCode = $this->call($step);
                $seconds = round(microtime(true) - $start, 1);

                if ($exitCode !== 0) {
                    $this->error("[$num/$total] ОШИБКА в команде $step (код $exitCode). Прогон остановлен.");
                    Log::error("kaspi:pipeline stopped at $step, exit code $exitCode");
                    $this->printFinalMessage(false);
                    return $exitCode;
                }

                $this->info("[$num/$total] Готово: $step (заняло {$seconds} сек.)");
                $this->newLine();
            } catch (Throwable $e) {
                $this->error("[$num/$total] ИСКЛЮЧЕНИЕ в команде $step: " . $e->getMessage());
                Log::error("kaspi:pipeline exception at $step: " . $e->getMessage());
                $this->printFinalMessage(false);
                return 1;
            }
        }

        $this->printFinalMessage(true);
        return 0;
    }

    protected function printFinalMessage(bool $success): void
    {
        $this->newLine();
        if ($success) {
            $this->info('====================================');
            $this->info('   Э КОНЧ, ВСЁ ГОТОВО. МОЖНО ВЫКЛЮЧАТЬ КОМП');
            $this->info('====================================');
        } else {
            $this->error('====================================');
            $this->error('  ПРОИЗОШЛА ОШИБКА. НЕ ВЫКЛЮЧАЙ КОМП,');
            $this->error('  НАПИШИ РОМАНУ / ПРИШЛИ СКРИН ЭКРАНА');
            $this->error('====================================');
        }
        $this->info('Окончание: ' . now()->format('Y-m-d H:i:s'));
    }
}