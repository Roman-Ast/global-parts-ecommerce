<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Разовая корректировка прода 2026-09-24 — возврат #1 (заказ №2046,
 * поставщик Тисс, 27250₸, "отказ клиента в каспи магазине") был закрыт
 * (status=completed) 23.09, но компенсация от поставщика так и осталась
 * supplier_refund_status=pending — Роман ввёл сумму "фактически
 * получено" (27250), но не переключил статус на "получена", засомневавшись,
 * успели ли деньги реально прийти. Деньги реально сели на баланс у Тисс
 * (зачётом, подтверждено Романом 2026-09-24). Раз CustomerReturnController
 * ::update() блокирует повторную обработку уже закрытых возвратов
 * (см. guard в начале метода) — через форму это больше не поправить,
 * нужно напрямую воспроизвести то, что сделал бы контроллер при
 * supplier_refund_status=received + supplier_refund_mode=credit (см.
 * CustomerReturnController::update()): создать supplier_credits и
 * проставить статус 'credited'. Сам баг (можно было закрыть возврат, не
 * отметив компенсацию) исправлен отдельным коммитом в этот же день
 * (CustomerReturnController — блокировка status=completed +
 * supplier_refund_status=pending), это уже не повторится для НОВЫХ
 * возвратов — здесь только точечная починка конкретной старой записи.
 *
 * Первый прогон 2026-09-24 упал на второй половине (UPDATE
 * customer_returns) из-за отдельного бага схемы — ENUM
 * supplier_refund_status не включал 'credited' (см.
 * 2026_09_24_000002_add_credited_to_supplier_refund_status_enum). У этой
 * миграции не было своей DB::transaction() (в отличие от аналогичного
 * кода в CustomerReturnController::update()) — INSERT в supplier_credits
 * успел закоммититься ДО того, как упал следующий UPDATE, и остался в
 * БД (подтверждено Романом: id=8). Раз миграция не отработала целиком —
 * Laravel её не засчитал как выполненную и переиграет заново, поэтому
 * два шага теперь проверяются НЕЗАВИСИМО (было — один общий guard,
 * из-за которого повторный прогон тихо пропустил бы уже назревший
 * UPDATE, раз INSERT уже есть).
 */
return new class extends Migration
{
    private const RETURN_ID = 1;
    private const SUPPLIER_ID = 30; // Тисс
    private const AMOUNT = 27250.00;

    public function up(): void
    {
        $alreadyCredited = DB::table('supplier_credits')
            ->where('source_table', 'customer_returns')
            ->where('source_id', self::RETURN_ID)
            ->exists();

        if (!$alreadyCredited) {
            DB::table('supplier_credits')->insert([
                'supplier_id' => self::SUPPLIER_ID,
                'amount' => self::AMOUNT,
                'source_table' => 'customer_returns',
                'source_id' => self::RETURN_ID,
                'comment' => 'Зачёт по возврату №' . self::RETURN_ID,
                'date' => '2026-09-23',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('customer_returns')
            ->where('id', self::RETURN_ID)
            ->where('supplier_refund_status', '!=', 'credited')
            ->update([
                'supplier_refund_status' => 'credited',
                'supplier_refund_date' => '2026-09-23',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Осознанно пусто — разовая корректировка, откатывать нечем/незачем.
    }
};
