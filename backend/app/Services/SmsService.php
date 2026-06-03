<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function sendSms(string $phone, string $message): bool
    {
        try {

            // format number to 2547XXXXXXXX
            $phone = $this->formatKenyanNumber($phone);

            $payload = [
                "SenderId" => config('services.onfon.sender_id'),
                "IsUnicode" => false,
                "IsFlash" => false,
                "MessageParameters" => [
                    [
                        "Number" => $phone,
                        "Text" => $message
                    ]
                ],
                "ApiKey"   => config('services.onfon.api_key'),
                "ClientId" => config('services.onfon.client_id'),
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'AccessKey' => config('services.onfon.access_key'),
            ])->post(config('services.onfon.url'), $payload);

            Log::info('ONFON SMS RESPONSE', [
                'phone' => $phone,
                'response' => $response->body()
            ]);

            $json = $response->json();

            if (($json['ErrorCode'] ?? 1) == 0) {
                Log::info('SMS SENT SUCCESSFULLY', ['phone' => $phone]);
                return true;
            }

            Log::error('SMS FAILED', [
                'phone' => $phone,
                'error' => $json['ErrorDescription'] ?? 'Unknown'
            ]);

            return false;

        } catch (\Throwable $e) {

            Log::error('SMS EXCEPTION', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    private function formatKenyanNumber(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '07')) {
            return '254' . substr($phone, 1);
        }

        if (str_starts_with($phone, '7')) {
            return '254' . $phone;
        }

        if (str_starts_with($phone, '254')) {
            return $phone;
        }

        return $phone;
    }
    public function sendLowStockAlert(string $medication, int $qty): bool
    {
        $phone = config('inventory.admin_nurse_phone', '254729572875');

        $message = "LOW STOCK ALERT: {$medication} remaining {$qty}. Please restock.";

        return $this->sendSms($phone, $message);
    }

}
