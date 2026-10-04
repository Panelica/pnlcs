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
 *
 * Twilio also limits sends on its side; a local provider sends whatever it is
 * asked to, at the operator's cost. So a number gets at most three codes in a
 * ten-minute window, and the five tries belong to the window, not the code: a
 * new code does not give back the tries already used.
 */
class SmsCodeVerifier implements PhoneVerifier
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const MAX_SENDS = 3;

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

        $window = $this->window($to);
        if ($window['sends'] >= self::MAX_SENDS || $window['attempts'] >= self::MAX_ATTEMPTS) {
            return ['status' => 'too_many_sends'];
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

        $window['sends']++;
        $this->keepWindow($to, $window);
        Cache::put($this->key($to), ['hash' => Hash::make($code)], now()->addMinutes(self::TTL_MINUTES));

        return ['status' => 'pending'];
    }

    public function check(string $to, string $code): ?array
    {
        $entry = Cache::get($this->key($to));
        if (! is_array($entry)) {
            return ['status' => 'expired', 'approved' => false];
        }

        $window = $this->window($to);
        if ($window['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($this->key($to));

            return ['status' => 'max_attempts_reached', 'approved' => false];
        }

        if (Hash::check(trim($code), (string) $entry['hash'])) {
            Cache::forget($this->key($to));
            Cache::forget($this->windowKey($to));

            return ['status' => 'approved', 'approved' => true];
        }

        $window['attempts']++;
        $this->keepWindow($to, $window);
        if ($window['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($this->key($to));

            return ['status' => 'max_attempts_reached', 'approved' => false];
        }

        return ['status' => 'pending', 'approved' => false];
    }

    private function key(string $to): string
    {
        return 'phone_verify:'.sha1($to);
    }

    /**
     * Codes sent and wrong tries for this number in the current window, which
     * starts at the first send and lasts TTL_MINUTES whatever happens in it.
     *
     * @return array{sends: int, attempts: int, until: int}
     */
    private function window(string $to): array
    {
        $window = Cache::get($this->windowKey($to));

        return is_array($window) && ($window['until'] ?? 0) > now()->getTimestamp()
            ? $window
            : ['sends' => 0, 'attempts' => 0, 'until' => now()->addMinutes(self::TTL_MINUTES)->getTimestamp()];
    }

    private function keepWindow(string $to, array $window): void
    {
        Cache::put($this->windowKey($to), $window, \Illuminate\Support\Carbon::createFromTimestamp($window['until']));
    }

    private function windowKey(string $to): string
    {
        return 'phone_verify_window:'.sha1($to);
    }
}
