<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; background-color:#f5f5f5; padding:20px; margin:0;">
    
    🏎 Новая заявка на подбор

    Источник: Форма VIN

    VIN: {{ $requestData['vincode'] ?: 'Не указан (есть фото)' }}

    Что нужно:
    {{ $requestData['spareparts'] }}

    Телефон:
    {{ $requestData['phone'] }}

    Примечание:
    {{ $requestData['note'] ?: '—' }}

    Фото: {{ !empty($photos) ? count($photos) : 0 }} шт.

</body>
</html>