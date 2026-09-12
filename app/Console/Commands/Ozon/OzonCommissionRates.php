<?php

namespace App\Console\Commands\Ozon;

/**
 * Комиссии Ozon по типам товаров. Роман усомнился в 47% ещё 2026-09-03
 * ("что-то слишком нереально") — тогда цифра была снята с ОДНОЙ тестовой
 * карточки. Живым прогоном 2026-09-12 (/v3/product/info/list →
 * commissions[]) проверено ЕЩЁ на 5 карточках из разных типов
 * (амортизаторы, ступицы, тормозные детали, разные ценовые диапазоны
 * 4500-17500 RUB) — везде percent=47, delivery_amount=25 RUB (FBS)
 * одинаково. Ставка подтверждена как стабильная по категории "Запчасти
 * для легковых автомобилей" в целом, не артефакт одной карточки — используется
 * в App\Services\OzonPriceCalculator для расчёта цены под реальный запуск продаж.
 *
 * Курс KZT→RUB — по-прежнему заглушка, не привязана к живому курсу ЦБ/биржи.
 */
class OzonCommissionRates
{
    /** Не проверено на живом курсе — ориентировочно, поправить перед боевым запуском. */
    public const EXCHANGE_RATE_KZT_TO_RUB = 0.19;

    /** Единственная измеренная живьём ставка (см. докблок выше) — под вопросом. */
    public const DEFAULT_COMMISSION_PERCENT = 47.0;

    /**
     * По ozon type_id — переопределения ставки для конкретных типов
     * товаров, когда Роман подтвердит реальные цифры (сейчас пусто,
     * всё падает на DEFAULT_COMMISSION_PERCENT).
     *
     * @var array<int, float>
     */
    public const BY_TYPE_ID = [
        // 970744063 => 47.0, // Амортизатор подвески — подтвердить
    ];

    public static function forType(int $typeId): float
    {
        return self::BY_TYPE_ID[$typeId] ?? self::DEFAULT_COMMISSION_PERCENT;
    }
}
