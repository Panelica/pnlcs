<?php

namespace App\Services\Hub;

use App\Models\Admin;
use App\Models\Setting;
use App\Services\Updates\Installation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The once-a-day ping to hub.pnlcs.com.
 *
 * It tells the PNLCS team how to reach this install about security and
 * updates: the company and admin contact details, the site address and the
 * versions. Nothing about the operator's customers, invoices, money, modules,
 * passwords, keys or paths. The full list is the docs "What is sent" page
 * (config/hub.php).
 *
 * The hub answering, failing or not existing changes nothing here: every
 * failure is logged and nothing is thrown. The answer is read for three
 * things only: signed notices (NoticeBoard), a request for a new identity,
 * and nothing else. No redirect is followed and nothing is downloaded.
 */
class HubPing
{
    public const PROTOCOL = 1;

    /** An answer larger than this is not read. */
    public const MAX_ANSWER = 65536;

    public function __construct(
        private readonly Installation $installation,
        private readonly NoticeBoard $notices,
    ) {}

    /**
     * Only a production install sends. This is not an off switch: it keeps
     * development copies and the PNLCS test suite from filling the hub.
     */
    public static function allowed(): bool
    {
        return app()->environment('production') && ! app()->runningUnitTests();
    }

    /** Send now. True when the hub answered. */
    public function send(int $timeout = 5): bool
    {
        $this->identity();
        Setting::set('hub_last_attempt_on', now()->toDateString(), 'hub');

        try {
            $response = Http::connectTimeout(min(3, $timeout))
                ->timeout($timeout)
                ->withOptions(['allow_redirects' => false])
                ->withHeaders(['User-Agent' => 'PNLCS/'.($this->installation->version() ?? 'unknown')])
                ->asJson()
                ->post((string) config('hub.ping_url'), $this->payload());
        } catch (\Throwable $e) {
            Log::warning('PNLCS hub ping failed', ['error' => $e->getMessage()]);

            return false;
        }

        if ($response->status() !== 200) {
            Log::warning('PNLCS hub ping was not accepted', ['status' => $response->status()]);

            return false;
        }

        $body = $response->body();
        $answer = strlen($body) <= self::MAX_ANSWER ? json_decode($body, true) : null;

        if (! is_array($answer)) {
            Log::warning('PNLCS hub answer could not be read');

            return false;
        }

        Setting::set('hub_last_ping_at', now()->toIso8601String(), 'hub');
        // The answers from the wizard (or the one-time box) go once.
        Setting::set('hub_answers_pending', '0', 'hub');

        $this->resetIdentity(($answer['reset_id'] ?? false) === true);

        if (is_array($answer['notices'] ?? null)) {
            try {
                $this->notices->receive($answer['notices']);
            } catch (\Throwable $e) {
                Log::warning('PNLCS hub notices could not be read', ['error' => $e->getMessage()]);
            }
        }

        return true;
    }

    /**
     * Is the daily ping due? Once a day, at an hour taken from the install id
     * so that installs spread across the day instead of all calling at once.
     */
    public function due(): bool
    {
        if (Setting::get('hub_last_attempt_on') === now()->toDateString()) {
            return false;
        }

        return now()->hour >= $this->hour();
    }

    /**
     * Without a cron the daily ping never runs. An admin page then sends it,
     * after the response, when the last good ping is a week old.
     */
    public function overdue(): bool
    {
        if (Setting::get('hub_last_attempt_on') === now()->toDateString()) {
            return false;
        }

        $last = Setting::get('hub_last_ping_at');

        return ! $last || \Carbon\Carbon::parse($last)->lessThanOrEqualTo(now()->subDays(7));
    }

    public function hour(): int
    {
        return (int) (hexdec(substr(hash('sha256', $this->identity()), 0, 6)) % 24);
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $admin = Admin::query()
            ->where('is_disabled', false)
            ->whereHas('role', fn ($q) => $q->where('is_full_admin', true))
            ->orderBy('id')
            ->first();

        $phone = $this->text(Setting::get('PhoneNumber'));
        $country = strtoupper($this->text(Setting::get('Country')));

        $payload = [
            'protocol' => self::PROTOCOL,
            'install_id' => $this->identity(),
            'install_secret' => (string) Setting::get('hub_install_secret'),
            'url' => $this->text(config('app.url')),
            'company_name' => $this->text(Setting::get('CompanyName') ?: config('app.name')),
            'company_email' => $this->text(Setting::get('Email')),
            'phone' => preg_match('#^[0-9+().\/ -]*$#', $phone) ? $phone : '',
            'country' => preg_match('/^[A-Z]{2}$/', $country) ? $country : '',
            'admin_name' => $this->text($admin?->full_name),
            'admin_email' => $this->text($admin?->email),
            'locale' => $this->text(Setting::get('DefaultLanguage')),
            'version' => (string) ($this->installation->version() ?? ''),
            'install_mode' => $this->installation->mode(),
            'docker' => Installation::inContainer(),
            'php' => PHP_VERSION,
            'db' => $this->databaseVersion(),
        ];

        // Live business or test copy, every time: it can be changed later in
        // Setup -> General. Not answered, not sent; the hub judges by address.
        $purpose = Setting::get('InstallPurpose');

        if (in_array($purpose, ['live', 'test'], true)) {
            $payload['purpose'] = $purpose;
        }

        if (Setting::get('hub_answers_pending') === '1') {
            $source = $this->text(Setting::get('hub_source'));

            if ($source !== '') {
                $payload['source'] = $source;
            }

            $payload['community_profile'] = Setting::get('hub_community_profile') === '1';
        }

        return $payload;
    }

    /** This install's id at the hub, made on first use with its secret. */
    public function identity(): string
    {
        $id = (string) Setting::get('hub_install_id');

        if (! Str::isUuid($id) || ! preg_match('/^[0-9a-f]{64}$/', (string) Setting::get('hub_install_secret'))) {
            $id = (string) Str::uuid();
            Setting::set('hub_install_id', $id, 'hub');
            Setting::set('hub_install_secret', bin2hex(random_bytes(32)), 'hub');
        }

        return $id;
    }

    /**
     * A copied database arrives at the hub from a second address with the
     * same id, and the hub asks it for a new one. Applied only when asked on
     * two different days: one broken or forged answer, or an address that
     * changed for an afternoon, must not cost an install its identity.
     */
    private function resetIdentity(bool $asked): void
    {
        $today = now()->toDateString();
        $seen = Setting::get('hub_reset_seen_on');

        if (! $asked) {
            if ($seen) {
                Setting::set('hub_reset_seen_on', '', 'hub');
            }

            return;
        }

        if ($seen && $seen !== $today) {
            Setting::set('hub_install_id', (string) Str::uuid(), 'hub');
            Setting::set('hub_install_secret', bin2hex(random_bytes(32)), 'hub');
            Setting::set('hub_reset_seen_on', '', 'hub');

            return;
        }

        Setting::set('hub_reset_seen_on', $today, 'hub');
    }

    /** One line of plain text: no control or format characters, at most 255. */
    private function text(mixed $value): string
    {
        $value = preg_replace('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', '', trim((string) $value)) ?? '';

        return mb_substr($value, 0, 255);
    }

    private function databaseVersion(): string
    {
        try {
            return $this->text(DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION));
        } catch (\Throwable) {
            return '';
        }
    }
}
