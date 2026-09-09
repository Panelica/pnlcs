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
            // A card that was declined, or money that was taken and could
            // not be matched to an invoice. The successful half of the
            // story was announced and the failed half went to the log
            // file only: the customer tried to pay, it did not work, and
            // nobody found out until they wrote in.
            'payment.failed',
            'ticket.opened',
            'ticket.replied',
            'service.activated',
            'service.suspended',
            'service.terminated',
            // The customer paid and the server module refused to build the
            // service. It sits in "pending" until somebody notices.
            'service.provision_failed',
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
            // A paid domain whose extension has no registrar module: the Manual
            // registrar marked it active and nobody contacted a registry.
            'domain.manual_registration_required',
            // The registrar prepayment is at the floor; renewals start being
            // refused one at a time from here.
            'registrar.balance_low',
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
                    'telegram' => $this->sendTelegram($rule->provider, $data),
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

    /**
     * Telegram.
     *
     * Email is where an alert goes to be read tomorrow morning. An order at
     * 2am, a ticket from an angry customer, a card that was declined - those
     * are worth a buzz in somebody's pocket, and Telegram is the cheapest way
     * to get one: a bot from @BotFather, a chat id, no third-party service in
     * between and nothing to pay.
     *
     * The message carries a link straight into the admin panel, because the
     * job of an alert is not to inform, it is to get someone to the screen
     * where they can act in one tap.
     */
    protected function sendTelegram(NotificationProvider $provider, array $data): void
    {
        $settings = $provider->settings ?? [];
        $token = trim((string) ($settings['bot_token'] ?? ''));
        $chatId = trim((string) ($settings['chat_id'] ?? ''));

        if ($token === '' || $chatId === '') {
            return;
        }

        $result = $this->pushToTelegram($token, $chatId, $this->telegramText($data));

        if (! $result['ok']) {
            // Never the token: a log file is read by more people than a
            // settings screen is, and this one would let any of them post to
            // the channel. Telegram's own description is enough to fix a
            // wrong chat id or a bot that was never added to the channel.
            Log::error('Telegram notification refused: '.$result['error']);
        }
    }

    /**
     * The message body: a bold subject, the text, and a way back into the panel.
     */
    private function telegramText(array $data): string
    {
        $lines = ['<b>'.e($data['subject'] ?? ($data['event_type'] ?? 'Notification')).'</b>'];

        $body = trim((string) ($data['message'] ?? ''));
        if ($body !== '') {
            $lines[] = e($body);
        }

        $link = $this->telegramLink($data);
        if ($link) {
            $lines[] = $link;
        }

        return implode("\n", $lines);
    }

    /**
     * The admin screen this alert is about, if the event named one.
     *
     * Built from route names rather than hand-written paths so that moving a
     * screen cannot leave the alerts pointing at a 404.
     */
    private function telegramLink(array $data): ?string
    {
        $targets = [
            'ticket_id' => 'admin.tickets.show',
            'invoice_id' => 'admin.invoices.show',
            'order_id' => 'admin.orders.show',
            'service_id' => 'admin.services.show',
            'client_id' => 'admin.clients.show',
        ];

        foreach ($targets as $key => $routeName) {
            if (! empty($data[$key]) && \Illuminate\Support\Facades\Route::has($routeName)) {
                return route($routeName, $data[$key]);
            }
        }

        return null;
    }

    /**
     * One call to the Bot API, shared with the "send a test message" button so
     * that a successful test proves the same path a real alert takes.
     *
     * @return array{ok: bool, error: string}
     */
    public function pushToTelegram(string $token, string $chatId, string $text): array
    {
        try {
            $response = Http::timeout(15)->post(
                'https://api.telegram.org/bot'.$token.'/sendMessage',
                [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]
            );

            if ($response->successful()) {
                return ['ok' => true, 'error' => ''];
            }

            return [
                'ok' => false,
                'error' => (string) ($response->json('description') ?? 'HTTP '.$response->status()),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
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
