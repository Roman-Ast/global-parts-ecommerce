<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказ №{{ $order->id }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f3ef;font-family:Arial,Helvetica,sans-serif;color:#222;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f3ef;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;max-width:600px;width:100%;">

                    {{-- Шапка --}}
                    <tr>
                        <td style="background:#1a1a1a;padding:20px 28px;">
                            <img src="{{ config('app.url') }}/images/logo1.png" alt="Global Parts" height="40" style="display:block;">
                        </td>
                    </tr>

                    {{-- Заголовок --}}
                    <tr>
                        <td style="padding:28px 28px 8px;">
                            <div style="font-size:20px;font-weight:700;color:#1a1a1a;">Заказ №{{ $order->id }} принят</div>
                            <div style="font-size:14px;color:#777;margin-top:4px;">{{ $order->date?->format('d.m.Y') }}, спасибо за заказ!</div>
                        </td>
                    </tr>

                    {{-- Контакты --}}
                    <tr>
                        <td style="padding:12px 28px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;color:#444;">
                                <tr>
                                    <td style="padding:4px 0;">Клиент</td>
                                    <td style="padding:4px 0;text-align:right;font-weight:600;">{{ $order->user->name ?? '' }}</td>
                                </tr>
                                @if ($order->customer_phone)
                                <tr>
                                    <td style="padding:4px 0;">Телефон</td>
                                    <td style="padding:4px 0;text-align:right;font-weight:600;">{{ $order->customer_phone }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="padding:4px 0;">Получение</td>
                                    <td style="padding:4px 0;text-align:right;font-weight:600;">
                                        {{ $order->delivery_type === 'pickup' ? 'Самовывоз' : 'Доставка' }}
                                    </td>
                                </tr>
                                @if ($order->address)
                                <tr>
                                    <td style="padding:4px 0;">Адрес</td>
                                    <td style="padding:4px 0;text-align:right;">{{ trim(($order->city ?? '') . ', ' . $order->address, ', ') }}</td>
                                </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    {{-- Товары --}}
                    <tr>
                        <td style="padding:20px 28px 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
                                <tr style="background:#f7f7f5;">
                                    <td style="padding:8px 10px;color:#888;font-size:11px;text-transform:uppercase;">Товар</td>
                                    <td style="padding:8px 10px;color:#888;font-size:11px;text-transform:uppercase;text-align:center;">Кол-во</td>
                                    <td style="padding:8px 10px;color:#888;font-size:11px;text-transform:uppercase;text-align:right;">Сумма</td>
                                </tr>
                                @foreach ($order->products as $product)
                                <tr style="border-bottom:1px solid #f0f0ec;">
                                    <td style="padding:9px 10px;">
                                        <div style="font-weight:600;">{{ $product->brand }} {{ $product->article }}</div>
                                        <div style="color:#888;font-size:12px;">{{ $product->name }}</div>
                                    </td>
                                    <td style="padding:9px 10px;text-align:center;">{{ $product->qty }}</td>
                                    <td style="padding:9px 10px;text-align:right;font-weight:600;">{{ number_format($product->itemSumWithMargine, 0, '', ' ') }} ₸</td>
                                </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    {{-- Итого --}}
                    <tr>
                        <td style="padding:16px 28px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding-top:12px;border-top:2px solid #1a1a1a;font-size:16px;font-weight:700;">Итого</td>
                                    <td style="padding-top:12px;border-top:2px solid #1a1a1a;font-size:18px;font-weight:800;text-align:right;color:#1e8449;">
                                        {{ number_format($order->sum_with_margine, 0, '', ' ') }} ₸
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Футер --}}
                    <tr>
                        <td style="background:#f7f7f5;padding:16px 28px;font-size:12px;color:#999;text-align:center;">
                            Global Parts — автозапчасти в Астане · shop.globalparts.kz
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
