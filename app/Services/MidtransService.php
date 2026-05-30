<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    protected string $serverKey;
    protected string $baseUrl;
    protected bool $isProduction;

    public function __construct()
    {
        $this->serverKey = config('services.midtrans.server_key', '');
        $this->isProduction = config('services.midtrans.is_production', false);
        $this->baseUrl = $this->isProduction
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';
    }

    /**
     * Configure Midtrans SDK globals (for Snap usage).
     */
    public function configureSnap(): void
    {
        \Midtrans\Config::$serverKey = $this->serverKey;
        \Midtrans\Config::$isProduction = $this->isProduction;
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        if (app()->environment('local', 'testing')) {
            \Midtrans\Config::$curlOptions = [
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTPHEADER => [],
            ];
        } else {
            \Midtrans\Config::$curlOptions = [
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => 1,
                CURLOPT_HTTPHEADER => [],
            ];
        }
    }

    /**
     * Request a refund via Midtrans API.
     *
     * @param string $orderId  The Midtrans order_id (e.g. BKG-42-1717012345)
     * @param int    $amount   Amount to refund in IDR
     * @param string $reason   Reason for refund
     * @return array{success: bool, data: mixed, message: string}
     */
    public function refundTransaction(string $orderId, int $amount, string $reason = 'Customer cancellation'): array
    {
        // In local/testing environments, skip the actual API call
        if (app()->environment('local', 'testing')) {
            Log::info("MidtransService: Mock refund for order {$orderId}, amount {$amount}");
            return [
                'success' => true,
                'data' => [
                    'status_code' => '200',
                    'status_message' => 'Success, refund request is approved (mock)',
                    'transaction_id' => 'mock-refund-' . time(),
                    'order_id' => $orderId,
                    'refund_amount' => $amount,
                ],
                'message' => 'Refund berhasil diproses (mode testing)',
            ];
        }

        try {
            $response = Http::withBasicAuth($this->serverKey, '')
                ->withOptions([
                    'verify' => $this->isProduction,
                ])
                ->post("{$this->baseUrl}/{$orderId}/refund", [
                    'refund_key' => "refund-{$orderId}-" . time(),
                    'amount' => $amount,
                    'reason' => $reason,
                ]);

            $body = $response->json();

            if ($response->successful() && in_array($body['status_code'] ?? '', ['200', '201'])) {
                Log::info("MidtransService: Refund successful for {$orderId}", $body);
                return [
                    'success' => true,
                    'data' => $body,
                    'message' => 'Refund berhasil diproses via Midtrans',
                ];
            }

            Log::warning("MidtransService: Refund failed for {$orderId}", $body);
            return [
                'success' => false,
                'data' => $body,
                'message' => $body['status_message'] ?? 'Refund gagal dari Midtrans',
            ];
        } catch (\Exception $e) {
            Log::error("MidtransService: Refund exception for {$orderId}: " . $e->getMessage());
            return [
                'success' => false,
                'data' => null,
                'message' => 'Error saat memproses refund: ' . $e->getMessage(),
            ];
        }
    }
}
