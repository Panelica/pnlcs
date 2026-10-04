<?php

namespace App\Contracts;

/**
 * Sends a code to a phone and checks it. Twilio Verify is one; SmsCodeVerifier,
 * which works with any SmsSender an addon provides, is another.
 */
interface PhoneVerifier
{
    public function enabled(): bool;

    /** @return array<string, mixed>|null null = the code could not be sent */
    public function start(string $to): ?array;

    /** @return array{status: ?string, approved: bool}|null null = could not ask, not "wrong code" */
    public function check(string $to, string $code): ?array;
}
