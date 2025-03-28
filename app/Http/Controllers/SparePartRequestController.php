<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\SparePartRequest;
use Laravel\Ui\Presets\React;

class SparePartRequestController extends Controller
{
    public function store(Request $request)
    {
        $requestData = [
            'vincode' => $request->vincode,
            'spareparts' => $request->spareparts,
            'email' => $request->email,
            'phone' => $request->phone,
            'note' => $request->note
        ];
        Mail::send(new SparePartRequest($requestData));

        return redirect('/home')
            ->with('message', 'Ваш запрос успешно отправлен, наш менеджер ответит вам выбранным вами способом, спасибо за обращение в Global Parts!')
            ->with('class', 'alert-success');
    }
}
