<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Запрос подбора по винкоду</title>
</head>
<body>
    <p>VINCODE: {{ $requestData['vincode'] }}</p>
    <p>Список запчастей: {{ $requestData['spareparts'] }}</p>
    <p>Телефон: {{ $requestData['phone'] }}</p>
    <p>Примечание: {{ $requestData['note'] }}</p>
    <h3 style="color: red;">Подбирай быстро и с энтузиазмом!!!</h3>
</body>
</html>