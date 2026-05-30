<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::failing(function (JobFailed $event) {
            $jobName = $event->job->resolveName();
            $errorMessage = $event->exception->getMessage();
            
            // 1. Log a critical alert
            Log::critical("QUEUE JOB FAILED: Job {$jobName} failed with error: {$errorMessage}", [
                'connection' => $event->connectionName,
                'exception' => $event->exception,
            ]);

            // 2. Alert to Webhook (Slack / Discord / Custom webhook) if configured
            $webhookUrl = env('QUEUE_ALERT_WEBHOOK');
            if ($webhookUrl) {
                try {
                    Http::post($webhookUrl, [
                        'text' => "🚨 *ALERT: Queue Job Failed!* 🚨\n\n" .
                                  "• *Job:* `{$jobName}`\n" .
                                  "• *Connection:* `{$event->connectionName}`\n" .
                                  "• *Error:* `{$errorMessage}`\n" .
                                  "• *Environment:* `" . app()->environment() . "`\n" .
                                  "• *Timestamp:* `" . now()->toDateTimeString() . "`"
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to send queue alert webhook: " . $e->getMessage());
                }
            }
        });
    }
}
