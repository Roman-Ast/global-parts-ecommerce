<?php

namespace App\Traits;

use App\Models\Customer;

trait HasCustomerLogic
{
    private function getOrCreateCustomer($phoneRaw, $name = null)
    {
        if (!$phoneRaw) return null;

        // Нормализация телефона (твоя логика)
        $phone = preg_replace('/\D+/', '', $phoneRaw);
        if (strlen($phone) === 11 && $phone[0] === '8') { $phone[0] = '7'; }
        if (strlen($phone) === 10) { $phone = '7' . $phone; }
        $phone = '+' . $phone;

        // Обновляем имя только если оно передано (чтобы не затереть старое)
        return Customer::updateOrCreate(
            ['phone' => $phone],
            array_filter([
                'name' => $name,
                'vin' => $request->vin ?? null,
                'city' => $request->city ?? null,
                'address' => $request->address ?? null,
                'car_model' => $request->car_model ?? null,
            ])
        );
    }
}