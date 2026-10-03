<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Статус лида, который реально проставил МЕНЕДЖЕР в CRM (lead.status + его
 * человекочитаемый ярлык через LeadStatuses::labelFor()) на момент ночного
 * анализа — просьба Романа 2026-10-03: не только вердикт, который
 * самостоятельно вынес LLM (outcome/decline_reason выше), а оба рядом, чтобы
 * можно было свериться — согласен ли менеджер вручную с тем, что увидела
 * модель по переписке, или нет. Чисто для сравнения/контроля качества LLM,
 * в сам анализ (промпт Claude) НЕ передаётся — модель размечает независимо
 * от того, что стоит в карточке.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demand_signals', function (Blueprint $table) {
            $table->string('manager_status', 50)->nullable()->after('outcome_amount');
            $table->string('manager_status_label', 100)->nullable()->after('manager_status');
        });
    }

    public function down(): void
    {
        Schema::table('demand_signals', function (Blueprint $table) {
            $table->dropColumn(['manager_status', 'manager_status_label']);
        });
    }
};
