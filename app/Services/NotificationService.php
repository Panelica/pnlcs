<?php

namespace App\Services;

use App\Models\NotificationProvider;
use App\Models\NotificationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Every event a rule can be created for.
     *
     * Anything dispatched but missing from here cannot be subscribed to on the
     * notifications screen, so it is delivered to nobody. Add the event here
     * in the same change that dispatches it.
     *
     * @var array<string, array<int, string>>
     */
    public const EVENT_TYPES = [
        'business' => [
            'client.created',
            'order.placed',
            'order.awaiting_acceptance',
            'invoice.created',
            'invoice.paid',
            'payment.notification_received',
            'ticket.opened',
            'ticket.replied',
            'service.activated',
            'service.suspended',
            'service.terminated',
        ],
        'system' => [
            'backup.failed',
            'module.failed',
            'module.failed_permanently',
            // Raised when a registrar refuses. Both need somebody to act: a
            // domain the customer has paid for is not registered, or one the
            // panel bills for is not renewed. An event that is dispatched and
            // not offered here cannot be subscribed to, so the alert goes
            // nowhere.
            'domain.registration_failed',
            'domain.renew_failed',
        ],
    ];

    /** @return array<int, string> */
    public static function eventTypes(): array
    {
        return array_merge(...array_values(self::EVENT_TYPES));
    }

    public function dispatch(string $eventType, array $data = []): void
    {
        $rules = NotificationRule::with('provider')
            ->where('event', $eventType)
            ->where('active', true)
            ->get();

        foreach ($rules as $rule) {
            if (! $rule->provider || ! $rule->provider->active) {
                continue;
            }

            try {
                match ($rule->provider->type) {
                    'email' => $this->sendEmail($rule, $data),
                    'slack' => $this->sendSlack($rule->provider, $data),
                    'webhook' => $this->sendWebhook($rule->provider, $data),
                    default => Log::warning("Unknown notification provider type: {$rule->provider->type}"),
                };
            } catch (\Throwable $e) {
                Log::error("Notification dispatch failed [{$rule->provider->type}]: ".$e->getMessage());
            }
        }
    }

    protected function sendEmail(NotificationRule $rule, array $data): void
    {
        $conditions = $rule->conditions ?? [];
        $to = $conditions['recipient_email'] ?? $data['email'] ?? null;
        if (! $to) {
            return;
        }

        $subject = $data['subject'] ?? "Notification: {$rule->event}";
        $body = $data['message'] ?? json_encode($data);

        Mail::raw($body, function ($mail) use ($to, $subject) {
            $mail->to($to)->subject($subject);
        });
    }

    protected function sendSlack(NotificationProvider $provider, array $data): void
    {
        $settings = $provider->settings ?? [];
        $webhookUrl = $settings['webhook_url'] ?? null;
        if (! $webhookUrl) {
            return;
        }

        Http::post($webhookUrl, [
            'text' => $data['message'] ?? json_encode($data),
            'username' => $settings['username'] ?? 'PNLCS',
            'icon_emoji' => $settings['icon'] ?? ':bell:',
        ]);
    }

    protected function sendWebhook(NotificationProvider $provider, array $data): void
    {
        $settings = $provider->settings ?? [];
        $url = $settings['url'] ?? null;
        if (! $url) {
            return;
        }

        $headers = [];
        if (! empty($settings['secret'])) {
            $headers['X-Webhook-Secret'] = $settings['secret'];
        }

        Http::withHeaders($headers)->post($url, [
            'event' => $data['event_type'] ?? 'notification',
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
