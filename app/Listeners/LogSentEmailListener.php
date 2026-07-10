<?php

namespace App\Listeners;

use App\Models\Client;
use App\Models\Email;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;

/**
 * Logs every outgoing mail into the emails table so clients can review
 * their message history (client area → Email History) and admins can see
 * what was actually delivered per client.
 */
class LogSentEmailListener
{
    public function handle(MessageSent $event): void
    {
        try {
            $message = $event->message; // Symfony\Component\Mime\Email

            $to = collect($message->getTo() ?? [])->map(fn ($a) => $a->getAddress())->implode(', ');
            if ($to === '') {
                return;
            }

            $client = Client::whereRaw('LOWER(email) = ?', [strtolower(trim(explode(',', $to)[0]))])->first();

            $body = $message->getHtmlBody() ?? $message->getTextBody() ?? '';
            if (is_resource($body)) {
                $body = '';
            }

            Email::create([
                'client_id' => $client?->id,
                'subject'   => $message->getSubject() ?? '(no subject)',
                'message'   => mb_substr((string) $body, 0, 60000),
                'date'      => now(),
                'to'        => mb_substr($to, 0, 255),
                'cc'        => mb_substr(collect($message->getCc() ?? [])->map(fn ($a) => $a->getAddress())->implode(', '), 0, 255) ?: null,
                'pending'   => false,
                'failed'    => false,
            ]);
        } catch (\Throwable $e) {
            // Logging must never break mail delivery.
            Log::warning('LogSentEmailListener failed: ' . $e->getMessage());
        }
    }
}
