<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Services\KaspiPriceCalculator;

class KaspiMarginPreviewCommand extends Command
{
    protected $signature   = 'kaspi:margin-preview {--output=storage/app/kaspi_margin_preview.xlsx}';
    protected $description = 'Рассчитать цены и маржу по kaspi_sku_test и выгрузить в Excel';

    // Каспи расходы
    const COMMISSION = 0.125; // 12.5%
    const TAX        = 0.04;  // 4%
    const LOGISTICS  = 1700;  // тенге

    // Прогрессивная шкала МИНИМАЛЬНОЙ маржи (% от себестоимости)
    const MIN_MARGIN_TIERS = [
        ['up_to' => 10000,        'min_pct' => 0.30],
        ['up_to' => 50000,        'min_pct' => 0.20],
        ['up_to' => 150000,       'min_pct' => 0.12],
        ['up_to' => PHP_INT_MAX,  'min_pct' => 0.08],
    ];

    /**
     * Категории товаров, где purchase_price у поставщика — это ВСЕГДА
     * полная себестоимость ОДНОЙ ЗАКУПКИ (одной коробки), и kaspi_qty
     * карточки на cost НИКАК НЕ ВЛИЯЕТ.
     *
     * Пример — тормозные колодки: у поставщика "кол-во: 1" и цена
     * purchase_price за коробку (физически 4 колодки внутри). На Kaspi
     * разные продавцы пишут kaspi_qty=1/2/4 в зависимости от трактовки,
     * но себестоимость одной коробки всегда purchase_price.
     *
     * cost = purchase_price (kaspi_qty игнорируется).
     */
    const FIXED_COST_KEYWORDS = [
        'колодки', // тормозные колодки — себестоимость = purchase_price, независимо от kaspi_qty карточки
    ];

    public function handle(): int
    {
        $this->info('Загружаем данные...');

        $rows = DB::select("
            SELECT
                t.sku,
                t.name,
                t.request_article,
                t.kaspi_qty,
                t.qty_suspicious,
                t.competitors_min_price,
                t.competitors_total,
                t.competitors_tomorrow_count,
                MIN(p.purchase_price) AS purchase_price,
                MIN(p.brand) AS brand,
                MIN(p.supplier_name) AS supplier_name,
                (
                    SELECT MIN(kc.price)
                    FROM kaspi_competitors kc
                    WHERE kc.kaspi_sku = t.sku
                    AND kc.delivery_duration = 'TOMORROW'
                    AND kc.preorder_days = 0
                ) AS tomorrow_min_price
            FROM kaspi_sku_test t
            LEFT JOIN kaspi_initial_products p ON p.sku = t.request_article
            WHERE p.purchase_price > 0
            GROUP BY
                t.id, t.sku, t.name, t.request_article, t.kaspi_qty,
                t.qty_suspicious, t.competitors_min_price,
                t.competitors_total, t.competitors_tomorrow_count
            ORDER BY brand, t.request_article
        ");

        if (empty($rows)) {
            $this->error('Нет данных — проверь связь kaspi_sku_test ↔ kaspi_initial_products');
            return 1;
        }

        $this->info('Строк: ' . count($rows) . '. Считаем цены...');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Маржа Каспи');

        // Заголовки
        $headers = [
            'A' => 'Артикул',
            'B' => 'Бренд',
            'C' => 'Название',
            'D' => 'Поставщик',
            'E' => 'Кол-во (Каспи)',
            'F' => 'Закуп (₸)',
            'G' => 'Конкурентов',
            'H' => 'Завтра у них',
            'I' => 'Мин. цена (все)',
            'J' => 'Мин. цена (завтра)',
            'K' => 'Эталон (₸)',
            'L' => 'Мин. допустимая (₸)',
            'M' => 'Наша цена (₸)',
            'N' => 'Маржа (₸)',
            'O' => 'Маржа %',
            'P' => 'Сценарий',
            'Q' => 'Флаг',
        ];

        foreach ($headers as $col => $title) {
            $sheet->setCellValue($col . '1', $title);
        }

        // Стиль заголовков
        $sheet->getStyle('A1:Q1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D6A4F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $rowNum = 2;
        $statRed = $statYellow = $statGreen = 0;
        $scenarioStats = [];

        foreach ($rows as $r) {
            $purchase = (float) $r->purchase_price;
            $qty      = (int)   ($r->kaspi_qty ?? 1);
            $qty      = max($qty, 1);

            // Себестоимость с учётом кол-ва (пара и т.д.)
            // BUNDLE_PRICED_KEYWORDS — если поставщик уже продаёт комплектом, не умножаем
            $isFixedCost = $this->isFixedCost($r->name);
            $cost = $isFixedCost ? $purchase : $purchase * $qty;

            // Эталонная цена по нашему калькулятору (KaspiPriceCalculator)
            $etalonPrice = (float) KaspiPriceCalculator::calculate($cost);

            // Минимально допустимая цена по прогрессивной шкале
            $minMarginPct = $this->getMinMarginPct($cost);
            $minNet       = $cost * $minMarginPct;
            $minPrice     = $this->calcSellPrice($cost, $minNet);

            $competitorsTotal    = (int) $r->competitors_total;
            $tomorrowCount       = (int) $r->competitors_tomorrow_count;
            $competitorMinAll    = $r->competitors_min_price ? (float) $r->competitors_min_price : null;
            $competitorMinTomorrow = $r->tomorrow_min_price ? (float) $r->tomorrow_min_price : null;

            // === ЛОГИКА ВЫБОРА ЦЕНЫ ===
            if ($competitorsTotal === 0 || $competitorMinAll === null) {
                // Нет конкурентов вообще — держим эталон
                $ourPrice = $etalonPrice;
                $scenario = 'Нет конкурентов';

            } elseif ($tomorrowCount === 0) {
                // Конкуренты есть, но ни у кого нет завтра — мы единственные с быстрой доставкой
                // Держим эталон
                $ourPrice = $etalonPrice;
                $scenario = 'Эталон (мы одни с завтра)';

            } else {
                // Есть конкуренты с доставкой завтра — реальная конкуренция
                // Сравниваемся именно с ними, не со всей массой
                if ($competitorMinTomorrow === null) {
                    // На всякий случай fallback — используем общий минимум
                    $competitorMinTomorrow = $competitorMinAll;
                }

                if ($etalonPrice <= $competitorMinTomorrow) {
                    // Наш эталон и так не дороже конкурента с завтра — держим эталон
                    $ourPrice = $etalonPrice;
                    $scenario = 'Эталон (конкурентен)';

                } else {
                    // Эталон дороже — пробуем встать чуть ниже конкурента с завтра
                    $beatPrice = floor($competitorMinTomorrow * 0.995);

                    if ($beatPrice >= $minPrice) {
                        $ourPrice = $beatPrice;
                        $scenario = 'Конкурент(завтра) -0.5%';
                    } else {
                        // Конкурент демпингует ниже нашего минимума — держим минимум
                        $ourPrice = $minPrice;
                        $scenario = 'Минимум (демпинг конкурента)';
                    }
                }
            }

            $ourPrice = round($ourPrice);

            // Чистая маржа
            $netMargin = $ourPrice
                - $cost
                - ($ourPrice * self::COMMISSION)
                - ($ourPrice * self::TAX)
                - self::LOGISTICS;

            $marginPct = $cost > 0 ? round(($netMargin / $cost) * 100, 1) : 0;

            // Флаг — по прогрессивному минимуму
            if ($netMargin < 0 || $marginPct < ($minMarginPct * 100)) {
                $flag = '🔴 РИСК';
                $statRed++;
            } elseif ($marginPct < ($minMarginPct * 100 * 1.5)) {
                $flag = '🟡 Низкая';
                $statYellow++;
            } else {
                $flag = '🟢 OK';
                $statGreen++;
            }

            $scenarioStats[$scenario] = ($scenarioStats[$scenario] ?? 0) + 1;

            $sheet->setCellValue('A' . $rowNum, $r->request_article);
            $sheet->setCellValue('B' . $rowNum, $r->brand);
            $sheet->setCellValue('C' . $rowNum, $r->name);
            $sheet->setCellValue('D' . $rowNum, $r->supplier_name);
            $sheet->setCellValue('E' . $rowNum, $r->kaspi_qty . ($isFixedCost ? ' 📦' : ''));
            $sheet->setCellValue('F' . $rowNum, $cost);
            $sheet->setCellValue('G' . $rowNum, $competitorsTotal);
            $sheet->setCellValue('H' . $rowNum, $tomorrowCount);
            $sheet->setCellValue('I' . $rowNum, $competitorMinAll ?? '—');
            $sheet->setCellValue('J' . $rowNum, $competitorMinTomorrow ?? '—');
            $sheet->setCellValue('K' . $rowNum, round($etalonPrice));
            $sheet->setCellValue('L' . $rowNum, round($minPrice));
            $sheet->setCellValue('M' . $rowNum, $ourPrice);
            $sheet->setCellValue('N' . $rowNum, round($netMargin));
            $sheet->setCellValue('O' . $rowNum, $marginPct . '%');
            $sheet->setCellValue('P' . $rowNum, $scenario);
            $sheet->setCellValue('Q' . $rowNum, $flag);

            // Цвет строки по флагу
            $fillColor = match(true) {
                str_contains($flag, 'РИСК')   => 'FFE5E5',
                str_contains($flag, 'Низкая') => 'FFF8DC',
                default                        => 'F0FFF4',
            };
            $sheet->getStyle("A{$rowNum}:Q{$rowNum}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillColor]],
            ]);

            $rowNum++;
        }

        // Итоговая строка
        $sheet->setCellValue('A' . $rowNum, 'ИТОГО: ' . count($rows) . ' позиций');
        $sheet->setCellValue('N' . $rowNum, '🔴 ' . $statRed . '  🟡 ' . $statYellow . '  🟢 ' . $statGreen);
        $sheet->getStyle('A' . $rowNum . ':Q' . $rowNum)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8E8E8']],
        ]);

        // Ширина колонок
        $widths = [
            'A'=>14,'B'=>12,'C'=>40,'D'=>14,'E'=>12,'F'=>12,'G'=>10,'H'=>10,
            'I'=>16,'J'=>16,'K'=>14,'L'=>16,'M'=>14,'N'=>12,'O'=>8,'P'=>26,'Q'=>10,
        ];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Фильтры
        $sheet->setAutoFilter('A1:Q1');

        // Закрепить шапку
        $sheet->freezePane('A2');

        // Бордеры на данные
        $sheet->getStyle("A1:Q" . ($rowNum))->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
            ],
        ]);

        $output = $this->option('output');
        $writer = new Xlsx($spreadsheet);
        $writer->save(base_path($output));

        $this->info("✅ Файл сохранён: {$output}");
        $this->info("🔴 Риск: {$statRed}  🟡 Низкая маржа: {$statYellow}  🟢 OK: {$statGreen}");
        $this->info('Распределение по сценариям:');
        foreach ($scenarioStats as $scenario => $count) {
            $this->line("  {$scenario}: {$count}");
        }

        return 0;
    }

    /**
     * Прогрессивный минимальный % маржи от себестоимости
     */
    private function getMinMarginPct(float $cost): float
    {
        foreach (self::MIN_MARGIN_TIERS as $tier) {
            if ($cost <= $tier['up_to']) {
                return $tier['min_pct'];
            }
        }
        return 0.08;
    }

    /**
     * Цена продажи, дающая targetNet чистыми после комиссии/налога/логистики
     */
    private function calcSellPrice(float $cost, float $targetNet): float
    {
        return ($cost + self::LOGISTICS + $targetNet) / (1 - self::COMMISSION - self::TAX);
    }

    /**
     * Проверяет, относится ли товар к категории с фиксированной
     * себестоимостью (cost = purchase_price, kaspi_qty игнорируется).
     */
    private function isFixedCost(string $title): bool
    {
        $titleLower = mb_strtolower($title);
        foreach (self::FIXED_COST_KEYWORDS as $keyword) {
            if (str_contains($titleLower, mb_strtolower($keyword))) {
                return true;
            }
        }
        return false;
    }
}
