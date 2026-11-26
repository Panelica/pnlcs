<?php

namespace App\Services\Sms;

use App\Models\Client;
use App\Models\Setting;
use App\Support\Countries;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Phone verification the other way round: the customer sends the code to us
 * on WhatsApp.
 *
 * The security page shows a six-digit code and a wa.me link that opens
 * WhatsApp with the code already typed to the operator's business number. The
 * customer presses send; WhatsApp Cloud API posts the message to our webhook
 * (signed with the Meta app secret), and the phone is verified when the code
 * is one we handed out and the message came from the number on the profile.
 *
 * Nothing is ever sent from our side, so the operator needs no message
 * template and pays nothing: Meta charges for the messages a business sends,
 * not for the ones it receives. What the operator does need is a WhatsApp
 * Business Platform number with the app subscribed to its "messages" webhook.
 *
 * A code lives in the cache for fifteen minutes and belongs to one client. A
 * message from any other number than the client's is not a verification: it
 * is recorded so the page can say why nothing happened.
 */
class WhatsappPhoneVerifier
{
    public const TTL_MINUTES = 15;

    public function enabled(): bool
    {
        return (bool) Setting::get('WhatsappVerifyEnabled', 0)
            && $this->businessNumber() !== ''
            && trim((string) Setting::get('WhatsappAppSecret', '')) !== ''
            && trim((string) Setting::get('WhatsappWebhookVerifyToken', '')) !== '';
    }

    /** The operator's WhatsApp number, digits only (wa.me wants no "+"). */
    public function businessNumber(): string
    {
        return preg_replace('/\D+/', '', (string) Setting::get('WhatsappBusinessNumber', '')) ?? '';
    }

    /**
     * A fresh code for this client, replacing any earlier one.
     *
     * @return array{code: string, link: string}|null null when the client has no usable phone
     */
    public function start(Client $client): ?array
    {
        if (! $this->enabled() || self::phoneCandidates($client) === []) {
            return null;
        }

        $this->forgetPending($client);

        do {
            $code = (string) random_int(100000, 999999);
        } while (Cache::has($this->codeKey($code)));

        $until = now()->addMinutes(self::TTL_MINUTES);
        Cache::put($this->codeKey($code), ['client_id' => $client->id], $until);
        Cache::put($this->clientKey($client), ['code' => $code, 'state' => 'pending'], $until);

        return ['code' => $code, 'link' => $this->link($code)];
    }

    /** @return array{code: string, link: string, state: string}|null the code still waiting for this client */
    public function pending(Client $client): ?array
    {
        $entry = Cache::get($this->clientKey($client));
        if (! is_array($entry) || empty($entry['code'])) {
            return null;
        }

        return ['code' => (string) $entry['code'], 'link' => $this->link((string) $entry['code']), 'state' => (string) ($entry['state'] ?? 'pending')];
    }

    public function link(string $code): string
    {
        $text = __('client.phone_verify.whatsapp_text', ['code' => $code, 'company' => company_name()]);

        return 'https://wa.me/'.$this->businessNumber().'?text='.rawurlencode($text);
    }

    /**
     * One inbound text message, as the webhook received it.
     *
     * @return string verified | mismatch | ignored
     */
    public function receive(string $from, string $body): string
    {
        if (! preg_match_all('/(?<!\d)(\d{6})(?!\d)/', $body, $found)) {
            return 'ignored';
        }

        foreach (array_unique($found[1]) as $code) {
            $entry = Cache::get($this->codeKey($code));
            $client = is_array($entry) ? Client::find($entry['client_id'] ?? null) : null;
            if (! $client) {
                continue;
            }

            $sender = preg_replace('/\D+/', '', $from);
            if (! in_array($sender, self::phoneCandidates($client), true)) {
                // Kept for the page, not acted on: the code was right but the
                // phone it came from is not the one on the profile.
                Cache::put($this->clientKey($client), ['code' => $code, 'state' => 'mismatch'], now()->addMinutes(self::TTL_MINUTES));
                Log::notice('WhatsApp phone verification from another number', ['client_id' => $client->id]);

                return 'mismatch';
            }

            $client->forceFill(['phone_verified_at' => now()])->save();
            $this->forgetPending($client);
            Log::info('Phone verified over WhatsApp', ['client_id' => $client->id]);

            return 'verified';
        }

        return 'ignored';
    }

    /**
     * The international numbers (digits only) the stored phone can stand for.
     *
     * Customers write the national trunk zero ("0532 ...") after a +90
     * prefix, or the whole international number in the number field, so more
     * than one reading is accepted; WhatsApp reports the sender without the
     * plus and without a trunk zero.
     *
     * @return list<string>
     */
    public static function phoneCandidates(Client $client): array
    {
        $raw = trim((string) $client->phone_number);
        $number = preg_replace('/\D+/', '', $raw) ?? '';
        if ($number === '') {
            return [];
        }

        $prefix = preg_replace('/\D+/', '', (string) ($client->phone_prefix ?: Countries::phonePrefix($client->country))) ?? '';
        $candidates = [];

        if (str_starts_with($raw, '+')) {
            $candidates[] = $number;
        } elseif (str_starts_with($number, '00')) {
            $candidates[] = substr($number, 2);
        } else {
            $national = ltrim($number, '0');
            if ($prefix !== '') {
                $candidates[] = $prefix.$national;
                // The prefix typed again in the number field: "90 532 ...".
                if (str_starts_with($number, $prefix)) {
                    $candidates[] = $number;
                }
            } else {
                $candidates[] = $number;
            }
        }

        return array_values(array_unique(array_filter($candidates, fn ($n) => strlen($n) >= 8 && strlen($n) <= 15)));
    }

    private function forgetPending(Client $client): void
    {
        $entry = Cache::pull($this->clientKey($client));
        if (is_array($entry) && ! empty($entry['code'])) {
            Cache::forget($this->codeKey((string) $entry['code']));
        }
    }

    private function codeKey(string $code): string
    {
        return 'whatsapp_verify_code:'.$code;
    }

    private function clientKey(Client $client): string
    {
        return 'whatsapp_verify_client:'.$client->id;
    }
}
