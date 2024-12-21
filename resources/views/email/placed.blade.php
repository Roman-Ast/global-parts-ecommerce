<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Заказ</title>
</head>
<body>
    <p>№ заказа: {{ $order->id }}</p>
    <p>Имя клиента: {{ $order->user->name }}</p>
    <p>Телефон: {{ $order->user->phone }}</p>
    @if ($order->customer_phone)
        <p>Телефон клиента: {{ $order->customer_phone }}</p>
    @endif
    <p> 
        <strong>Сумма заказа: {{ $order->sum}}</strong>
    </p>
    <h3 style="color: red;">Проверь админку!!!</h3>
</body>
</html>