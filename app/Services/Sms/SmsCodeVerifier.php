<?php

namespace App\Services\Sms;

use App\Contracts\PhoneVerifier;
use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Phone verification through whatever SmsSender is installed: a six-digit code
 * of our own, kept hashed in the cache for ten minutes and good for five tries,
 * as Twilio Verify does it for us.
 */
class SmsCodeVerifier implements PhoneVerifier
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public function __construct(private ?SmsSender $sender = null) {}

    public function enabled(): bool
    {
        return $this->sender !== null;
    }

    public function start(string $to): ?array
    {
        if (! $this->sender) {
            return null;
        }

        $code = (string) random_int(100000, 999999);
        $text = __('client.phone_verify.sms_text', ['code' => $code, 'company' => company_name()]);

        try {
            if (! $this->sender->send($to, $text)) {
                return null;
            }
        } catch (\Throwable $e) {
            Log::warning('SMS sender failed: '.$e->getMessage());

            return null;
        }

        Cache::put($this->key($to), ['hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes(self::TTL_MINUTES));

        return ['status' => 'pending'];
    }

    public function check(string $to, string $code): ?array
    {
        $entry = Cache::get($this->key($to));
        if (! is_array($entry)) {
            return ['status' => 'expired', 'approved' => false];
        }

        if (Hash::check(trim($code), (string) $entry['hash'])) {
            Cache::forget($this->key($to));

            return ['status' => 'approved', 'approved' => true];
        }

        $entry['attempts'] = (int) $entry['attempts'] + 1;
        if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($this->key($to));

            return ['status' => 'max_attempts_reached', 'approved' => false];
        }
        Cache::put($this->key($to), $entry, now()->addMinutes(self::TTL_MINUTES));

        return ['status' => 'pending', 'approved' => false];
    }

    private function key(string $to): string
    {
        return 'phone_verify:'.sha1($to);
    }
}
