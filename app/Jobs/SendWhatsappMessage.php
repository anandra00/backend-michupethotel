<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendWhatsappMessage implements ShouldQueue
{
    use Queueable;

    public $phone;

    public $message;

    /**
     * Create a new job instance.
     */
    public function __construct(string $phone, string $message)
    {
        $this->phone = $phone;
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $token = config('services.fonnte.token');

        if (empty($token)) {
            // Fallback to logs if no Fonnte token is set
            Log::info('=== MOCKED WHATSAPP MESSAGE (QUEUE) ===');
            Log::info("To: {$this->phone}");
            Log::info("Message: \n{$this->message}");
            Log::info('===============================');

            return;
        }

        try {
            $curl = curl_init();

            // Enable SSL verification in production, disable only in local dev
            $sslVerify = app()->environment('local') ? 0 : 2;

            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.fonnte.com/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30, // 30 second timeout to prevent queue worker hangs
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_SSL_VERIFYHOST => $sslVerify,
                CURLOPT_SSL_VERIFYPEER => $sslVerify ? true : false,
                CURLOPT_POSTFIELDS => [
                    'target' => $this->phone,
                    'message' => $this->message,
                    'countryCode' => '62',
                ],
                CURLOPT_HTTPHEADER => [
                    'Authorization: '.$token,
                ],
            ]);

            $response = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                Log::error('cURL Error when sending WhatsApp: '.$err);
            } elseif ($httpcode >= 200 && $httpcode < 300) {
                Log::info("WhatsApp message queued successfully to {$this->phone}. Fonnte Response: ".$response);
            } else {
                Log::error("Failed to send WhatsApp message. HTTP Code: {$httpcode}, Fonnte response: ".$response);
            }
        } catch (\Exception $e) {
            Log::error('Exception when sending WhatsApp message: '.$e->getMessage());
        }
    }
}
