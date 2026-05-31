<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppNotification extends Notification
{
    use Queueable;

    protected $title;

    protected $message;

    protected $type;

    protected $url;

    public function __construct($title, $message, $type = 'info', $url = null)
    {
        $this->title = $title;
        $this->message = $message;
        $this->type = $type;
        $this->url = $url;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        try {
            $this->sendWebPush($notifiable);
        } catch (\Exception $e) {
            \Log::error("WebPush dynamic dispatch failed: " . $e->getMessage());
        }

        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'url' => $this->url,
        ];
    }

    /**
     * Send Web Push notification to all active browser subscriptions of the user.
     */
    protected function sendWebPush(object $notifiable): void
    {
        if (!$notifiable instanceof \App\Models\User) {
            return;
        }

        $subscriptions = \App\Models\PushSubscription::where('user_id', $notifiable->id)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $publicKey = env('VAPID_PUBLIC_KEY');
        $privateKey = env('VAPID_PRIVATE_KEY');

        if (empty($publicKey) || empty($privateKey)) {
            \Log::info("WebPush: Notification log fallback (VAPID keys not configured in .env) for {$notifiable->name}: Title: {$this->title}");
            return;
        }

        try {
            $auth = [
                'VAPID' => [
                    'subject' => 'mailto:info@michumeowstay.com',
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ]
            ];

            $webPush = new \Minishlink\WebPush\WebPush($auth);
            
            $payload = json_encode([
                'title' => $this->title,
                'body' => $this->message,
                'url' => $this->url ?? '/dashboard',
            ]);

            foreach ($subscriptions as $sub) {
                $subscription = \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->public_key,
                    'authToken' => $sub->auth_token,
                ]);

                $webPush->queueNotification($subscription, $payload);
            }

            foreach ($webPush->flush() as $report) {
                if (!$report->isSuccess()) {
                    \Log::warning("WebPush failed: {$report->getReason()}");
                    if (in_array($report->getReason(), ['expired', 'gone'])) {
                        \App\Models\PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error("Error sending WebPush notification: " . $e->getMessage());
        }
    }
}
