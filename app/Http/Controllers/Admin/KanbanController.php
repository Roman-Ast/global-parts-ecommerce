<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KanbanController extends Controller
{
    /**
     * Отображение Канбан-доски.
     */
    public function index()
    {
        // 1. Определяем жесткий порядок колонок нашей воронки
        $columns = [
            'new'       => ['title' => 'Новые', 'color' => 'bg-blue-500'],
            'selection' => ['title' => 'Подбор запчастей', 'color' => 'bg-yellow-500'],
            'offer'     => ['title' => 'КП Отправлено', 'color' => 'bg-indigo-500'],
            'thinking'  => ['title' => 'Думает / Пауза', 'color' => 'bg-orange-500'],
            'payment'   => ['title' => 'Ожидаем оплату', 'color' => 'bg-green-500'],
            'delivery'  => ['title' => 'Доставка / Логистика', 'color' => 'bg-purple-500'],
        ];

        // 2. Получаем лидов, подгружая последнее сообщение для превью в карточке
        $leads = WhatsappLead::with(['messages' => function($query) {
                $query->latest()->limit(1);
            }])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->groupBy('status');

        return view('admin.kanban', [
            'leadsByStatus' => $leads,
            'columns'       => $columns
        ]);
    }

    /**
     * Обновление статуса лида (вызывается при Drag-and-Drop).
     */
    public function updateStatus(Request $request)
    {
        $request->validate([
            'lead_id' => 'required|exists:whatsapp_leads,id',
            'status'  => 'required|string',
            'objection' => 'nullable|string', // Для этапа "Думает"
        ]);

        try {
            DB::beginTransaction();

            $lead = WhatsappLead::findOrFail($request->lead_id);
            $oldStatus = $lead->status;
            $newStatus = $request->status;

            // Обновляем статус
            $lead->status = $newStatus;
            
            // Если перенесли в "Думает", записываем причину если она пришла
            if ($newStatus === 'thinking' && $request->has('objection')) {
                $lead->objection_type = $request->objection;
            }

            $lead->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Клиент {$lead->phone} переведен в этап: " . $newStatus,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении статуса: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получение детальной инфы по лиду для модального окна на Канбане.
     */
    public function getLeadDetails($id)
    {
        $lead = WhatsappLead::with('messages')->findOrFail($id);
        return response()->json($lead);
    }
}