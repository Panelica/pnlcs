<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google reCAPTCHA v2 ("I'm not a robot" checkbox), written against the
 * published server-side API (developers.google.com/recaptcha/docs/verify):
 * POST https://www.google.com/recaptcha/api/siteverify as a form with
 * secret, response and an optional remoteip, answered with JSON carrying
 * success (bool) and error-codes.
 *
 * Off until an operator saves both keys of their own, so a fresh install
 * shows no widget and no credential of ours is ever shipped in a release.
 * A switch that is on with a key missing counts as off: a widget that cannot
 * be answered would only lock every visitor out of the form.
 */
class RecaptchaService
{
    public const ENDPOINT = 'https://www.google.com/recaptcha/api/siteverify';

    /** The field name the Google widget posts its answer under. */
    public const FIELD = 'g-recaptcha-response';

    /**
     * Each guarded form has its own switch, so an operator can protect the
     * public contact form without asking signed-in customers to solve a
     * challenge too, or the other way round. The keys are shared.
     */
    public const FORMS = [
        'contact' => 'RecaptchaEnabled',
        'tickets' => 'RecaptchaTicketsEnabled',
    ];

    public function enabled(string $form = 'contact'): bool
    {
        $switch = self::FORMS[$form] ?? null;

        return $switch !== null
            && (string) Setting::get($switch, '0') === '1'
            && $this->configured();
    }

    /** Both keys saved; without either, no form can use the challenge. */
    public function configured(): bool
    {
        return $this->siteKey() !== ''
            && trim((string) Setting::get('RecaptchaSecretKey', '')) !== '';
    }

    public function siteKey(): string
    {
        return trim((string) Setting::get('RecaptchaSiteKey', ''));
    }

    /**
     * Whether Google confirms the visitor solved the challenge.
     *
     * Fails closed: an empty answer, a refusal, or Google being unreachable
     * all return false. Letting submissions through whenever the check
     * cannot be made is exactly the gap a spammer would wait for.
     */
    public function verify(?string $token, ?string $ip = null): bool
    {
        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        $body = [
            'secret' => trim((string) Setting::get('RecaptchaSecretKey', '')),
            'response' => $token,
        ];
        if ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP)) {
            $body['remoteip'] = $ip;
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::ENDPOINT, $body);

            if (! $response->successful()) {
                Log::warning('reCAPTCHA verification failed', ['status' => $response->status()]);

                return false;
            }

            if ($response->json('success') !== true) {
                Log::info('reCAPTCHA rejected a submission', [
                    'error-codes' => $response->json('error-codes'),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA verification unreachable: '.$e->getMessage());

            return false;
        }
    }
}
