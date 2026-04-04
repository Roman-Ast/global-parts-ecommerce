<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Mail\SparePartRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SparePartRequestController extends Controller
{
    public function storerr(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vincode' => 'nullable|string|max:255',
            'spareparts' => 'required|string|max:2000',
            'phone' => 'required|string|max:50',
            'note' => 'nullable|string|max:1000',

            'tech_passport' => 'nullable|array|max:5',
            'tech_passport.*' => 'file|image|mimes:jpg,jpeg,png,webp|max:4096',
        ], [
            'spareparts.required' => 'Укажите, какие запчасти нужны.',
            'spareparts.max' => 'Поле "Какие запчасти нужны" слишком длинное.',
            'phone.required' => 'Укажите телефон.',
            'phone.max' => 'Телефон слишком длинный.',
            'vincode.max' => 'VIN слишком длинный.',
            'note.max' => 'Примечание слишком длинное.',

            'tech_passport.array' => 'Фото должны передаваться списком.',
            'tech_passport.max' => 'Можно загрузить максимум 5 фото.',
            'tech_passport.*.image' => 'Можно загружать только изображения.',
            'tech_passport.*.mimes' => 'Допустимые форматы фото: jpg, jpeg, png, webp.',
            'tech_passport.*.max' => 'Каждое фото должно быть не больше 4 МБ.',
        ]);

        $validator->after(function ($validator) use ($request) {
            $vin = trim((string) $request->input('vincode', ''));
            $hasPhotos = $request->hasFile('tech_passport') && count($request->file('tech_passport')) > 0;

            if ($vin === '' && !$hasPhotos) {
                $validator->errors()->add('vincode', 'Укажите VIN или прикрепите хотя бы одно фото.');
            }
        });

        $validated = $validator->validate();

        $requestData = [
            'vincode' => trim((string) ($validated['vincode'] ?? '')),
            'spareparts' => trim((string) $validated['spareparts']),
            'phone' => trim((string) $validated['phone']),
            'note' => trim((string) ($validated['note'] ?? '')),
        ];

        $photos = $request->file('tech_passport', []);

        //Mail::send(new SparePartRequest($requestData, $photos));

        // 2. ОТПРАВКА В TELEGRAM
        try {
            $token = env('TELEGRAM_BOT_TOKEN');
            $chatId = env('TELEGRAM_CHAT_ID');

            // подготовка номера для WhatsApp
            $phoneClean = preg_replace('/[^0-9]/', '', $requestData['phone']);

            $whatsappMessage = 'Здравствуйте! Спасибо за обращение в Global Parts. Получили ваш запрос по запчастям на VIN: '
                . ($requestData['vincode'] ?: 'не указан')
                . '. Сейчас проверим наличие и ответим в течение 10-15 минут!.';

            $whatsappLink = 'https://wa.me/'.$phoneClean.'?text='.urlencode($whatsappMessage);


            // текст сообщения
            $text .= "\n━━━━━━━━━━━━━━━━━━━━\n\n";
            $text = "🏎 <b>Новая заявка: Global Parts</b>\n\n"; // Двойной перенос после заголовка

            $text .= "📞 <b>Телефон:</b> {$requestData['phone']}\n"; // Одинарный перенос
            $text .= "🔍 <b>VIN:</b> <code>" . ($requestData['vincode'] ?: 'не указан') . "</code>\n";
            $text .= "🛠 <b>Запчасти:</b>\n" . e($requestData['spareparts']) . "\n\n"; // Перенос перед списком и двойной после

            if ($requestData['note']) {
                $text .= "📝 <b>Примечание:</b>\n<i>" . e($requestData['note']) . "</i>\n\n";
            }

            // Отправляем текст
            $keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '💬 Ответить в WhatsApp',
                            'url' => $whatsappLink
                        ]
                    ]
                ]
            ];

            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => json_encode($keyboard)
            ]);

            // Отправляем файлы, если они есть
            if (!empty($photos)) {
                foreach ($photos as $file) {
                    $extension = strtolower($file->getClientOriginalExtension());
                    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp']);
                    
                    $method = $isImage ? 'sendPhoto' : 'sendDocument';
                    $fieldName = $isImage ? 'photo' : 'document';

                    // Используем attach правильно
                    Http::attach(
                        $fieldName, 
                        file_get_contents($file->getRealPath()), 
                        $file->getClientOriginalName()
                    )->post("https://api.telegram.org/bot{$token}/{$method}", [
                        'chat_id' => $chatId,
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error("Ошибка Telegram: " . $e->getMessage());
        }

        return redirect('/home')
            ->with('message', 'Cпасибо за обращение в Global Parts! Ваш запрос успешно отправлен, наш менеджер ответит вам в ближайшее время.')
            ->with('class', 'alert-success');
    }

    public function store(Request $request)
    {
        // 1. Валидация (твои правила)
        $validator = Validator::make($request->all(), [
            'vincode' => 'nullable|string|max:255',
            'spareparts' => 'required|string|max:2000',
            'phone' => 'required|string|max:50',
            'note' => 'nullable|string|max:1000',
            'tech_passport' => 'nullable|array|max:5',
            'tech_passport.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:5120', // Добавил PDF в список
        ], [
            'spareparts.required' => 'Укажите, какие запчасти нужны.',
            'phone.required' => 'Укажите телефон.',
            'vincode.max' => 'VIN слишком длинный.',
            'tech_passport.max' => 'Можно загрузить максимум 5 файлов.',
            'tech_passport.*.mimes' => 'Допустимые форматы: фото (jpg, png, webp) или PDF.',
        ]);

        $validator->after(function ($validator) use ($request) {
            $vin = trim((string) $request->input('vincode', ''));
            $hasPhotos = $request->hasFile('tech_passport') && count($request->file('tech_passport')) > 0;
            if ($vin === '' && !$hasPhotos) {
                $validator->errors()->add('vincode', 'Укажите VIN или прикрепите хотя бы одно фото.');
            }
        });

        $validated = $validator->validate();

        $requestData = [
            'vincode' => trim((string) ($validated['vincode'] ?? '')),
            'spareparts' => trim((string) $validated['spareparts']),
            'phone' => trim((string) $validated['phone']),
            'note' => trim((string) ($validated['note'] ?? '')),
        ];

        $photos = $request->file('tech_passport', []);

        // 2. ОТПРАВКА В TELEGRAM
        try {
            $token = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_CHAT_ID');

        // Подготовка данных
        $phoneClean = preg_replace('/[^0-9]/', '', $requestData['phone']);
        $whatsappMessage = "Здравствуйте! Спасибо за обращение в Global Parts. Получили ваш запрос по запчастям на VIN: " . ($requestData['vincode'] ?: 'не указан') . ". Сейчас проверим наличие и ответим в течение 10-15 минут.";
        $whatsappLink = 'https://wa.me/'.$phoneClean.'?text='.urlencode($whatsappMessage);

        // Текст сообщения (БЕЗ сложного HTML для начала, чтобы не ломалось)
        $text = "🏎 <b>Новая заявка: Global Parts</b>\n\n";
        $text .= "📞 <b>Тел:</b> " . e($requestData['phone']) . "\n";
        $text .= "🔍 <b>VIN:</b> <code>" . e($requestData['vincode'] ?: 'не указан') . "</code>\n";
        $text .= "🛠 <b>Запчасти:</b> " . e($requestData['spareparts']) . "\n";
        if ($requestData['note']) {
            $text .= "📝 <b>Прим:</b> " . e($requestData['note']) . "\n";
        }

        $keyboard = [
            'inline_keyboard' => [[
                ['text' => '💬 Ответить в WhatsApp', 'url' => $whatsappLink]
            ]]
        ];

        if (!empty($photos)) {
            // САМЫЙ НАДЕЖНЫЙ СПОСОБ: Отправляем текст ПЕРВЫМ, а файлы СЛЕДОМ
            // Так мы избегаем лимитов caption (1024 символа) и ошибок альбомов
            
            // 1. Шлем текст с кнопкой
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard)
            ]);

            // 2. Шлем файлы по одному (так надежнее всего для фото + PDF)
            foreach ($photos as $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp']);
                
                $method = $isImage ? 'sendPhoto' : 'sendDocument';
                $fieldName = $isImage ? 'photo' : 'document';

                Http::attach(
                    $fieldName, 
                    file_get_contents($file->getRealPath()), 
                    $file->getClientOriginalName()
                )->post("https://api.telegram.org/bot{$token}/{$method}", [
                    'chat_id' => $chatId,
                ]);
            }
        } else {
            // Если файлов нет — просто сообщение
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard),
                'disable_web_page_preview' => true
            ]);
        }

        } catch (\Exception $e) {
            \Log::error("Ошибка Telegram: " . $e->getMessage());
        }

        // Резервная отправка на почту (если нужно, раскомментируй)
        // Mail::send(new SparePartRequest($requestData, $photos));

        return redirect('/home')
            ->with('message', 'Cпасибо за обращение в Global Parts! Запрос успешно отправлен, скоро ответим.')
            ->with('class', 'alert-success');
    }
}
