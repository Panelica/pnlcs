<?php

namespace App\Services\Sms;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Twilio Verify v2, written against the published API
 * (twilio.com/docs/verify/api): form-encoded POSTs with HTTP Basic auth
 * (account SID / auth token) to
 * Services/{ServiceSid}/Verifications  - To (E.164) + Channel to send a code -
 * Services/{ServiceSid}/VerificationCheck - To + Code to check one.
 * A check answers status approved|pending|... and a legacy boolean `valid`;
 * Twilio deletes the verification after approval, expiry (10 minutes) or max
 * attempts, at which point the check endpoint answers 404.
 */
class TwilioVerifyClient
{
    public const BASE = 'https://verify.twilio.com/v2/Services/';

    public function enabled(): bool
    {
        return (bool) Setting::get('TwilioVerifyEnabled', 0)
            && trim((string) Setting::get('TwilioAccountSid', '')) !== ''
            && trim((string) Setting::get('TwilioAuthToken', '')) !== ''
            && trim((string) Setting::get('TwilioVerifyServiceSid', '')) !== '';
    }

    /** "+48 123 456 789" and friends into E.164, or null when unusable. */
    public static function e164(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? '+'.$digits : null;
    }

    /** @return array{sid: ?string, status: ?string}|null */
    public function start(string $to): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = $this->request()->post($this->url('Verifications'), [
                'To' => $to,
                'Channel' => 'sms',
            ]);

            if (! $response->successful()) {
                Log::warning('Twilio Verify start failed', ['status' => $response->status(), 'to' => $to]);

                return null;
            }

            return ['sid' => $response->json('sid'), 'status' => $response->json('status')];
        } catch (\Throwable $e) {
            Log::warning('Twilio Verify unreachable: '.$e->getMessage());

            return null;
        }
    }

    /** @return array{status: ?string, approved: bool}|null null = could not ask, not "wrong code" */
    public function check(string $to, string $code): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = $this->request()->post($this->url('VerificationCheck'), [
                'To' => $to,
                'Code' => $code,
            ]);

            // 404 is a definite answer, not an outage: the verification is
            // gone (expired, already approved, or attempts used up).
            if ($response->status() === 404) {
                return ['status' => 'expired', 'approved' => false];
            }
            if (! $response->successful()) {
                Log::warning('Twilio Verify check failed', ['status' => $response->status()]);

                return null;
            }

            $status = $response->json('status');

            return ['status' => $status, 'approved' => $status === 'approved'];
        } catch (\Throwable $e) {
            Log::warning('Twilio Verify unreachable: '.$e->getMessage());

            return null;
        }
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withBasicAuth(
            trim((string) Setting::get('TwilioAccountSid')),
            trim((string) Setting::get('TwilioAuthToken'))
        )->asForm()->timeout(10);
    }

    private function url(string $resource): string
    {
        return self::BASE.trim((string) Setting::get('TwilioVerifyServiceSid')).'/'.$resource;
    }
}
