<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappLead;
use Illuminate\Http\Request;

class WhatsappController extends Controller
{
    public function index()
    {
        // Берем всех лидов, у которых есть сообщения, сортируем по свежести
        $leads = WhatsappLead::with(['messages' => function($q) {
            $q->latest();
        }])->orderBy('last_seen_at', 'desc')->get();

        return view('admin.whatsapp.index', compact('leads'));
    }

    public function show(WhatsappLead $lead)
    {
        // Нам СНОВА нужен список всех чатов для левой панели
        $leads = WhatsappLead::with(['messages' => function($q) {
            $q->latest();
        }])->orderBy('last_seen_at', 'desc')->get();

        // Загружаем сообщения именно для открытого чата
        $messages = $lead->messages()->orderBy('created_at', 'asc')->get();

        // Передаем и список ($leads), и открытый чат ($lead), и его сообщения ($messages)
        return view('admin.whatsapp.chat', compact('leads', 'lead', 'messages'));
    }
}
