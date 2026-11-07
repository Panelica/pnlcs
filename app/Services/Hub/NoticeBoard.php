<?php

namespace App\Services\Hub;

use App\Models\Admin;
use App\Models\Setting;
use App\Services\Updates\Installation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notices from the PNLCS team, as they arrive in the hub's answer to the
 * daily ping, and what an admin panel shows of them.
 *
 * A notice is trusted only when it carries the PNLCS notice key's signature
 * (config/hub.php). The hub passes notices on; it cannot write one. So the
 * signature is checked first, on the exact bytes that were signed, and only
 * then is the text decoded. Nothing outside the signed statement is read.
 *
 * Every notice ends: expires_at is signed and at most 90 days after
 * issued_at, so an old signed notice cannot be sent again for ever. A signed
 * revocation removes a notice for good, and the revoked id is kept even after
 * the notice has expired, so a hub sending the old notice again cannot bring
 * it back.
 */
class NoticeBoard
{
    public const MAX_DAYS = 90;

    /** Hosts a notice may link to. Exact match, never a prefix. */
    private const URL_HOSTS = ['pnlcs.com', 'docs.pnlcs.com', 'community.pnlcs.com', 'github.com'];

    public function __construct(private readonly ?string $publicKey = null) {}

    /**
     * Take the notices from one hub answer. Each item is
     * {statement, signature}; anything else is ignored.
     *
     * @param  array<int, mixed>  $items
     */
    public function receive(array $items): void
    {
        $notices = $this->stored();
        $revoked = $this->revoked();

        foreach ($items as $item) {
            if (! is_array($item) || ! is_string($item['statement'] ?? null) || ! is_string($item['signature'] ?? null)) {
                continue;
            }

            $data = $this->verified($item['statement'], $item['signature']);

            if ($data === null) {
                continue;
            }

            if (array_key_exists('revokes', $data)) {
                if (is_string($data['revokes']) && $this->validId($data['revokes'])) {
                    $revoked[] = $data['revokes'];
                }

                continue;
            }

            $notice = $this->notice($data);

            if ($notice !== null) {
                $notices[$notice['id']] = $notice;
            }
        }

        $revoked = array_values(array_unique($revoked));
        $notices = array_filter($notices, fn ($n) => ! in_array($n['id'], $revoked, true) && ! $this->expired($n));

        Setting::set('hub_revoked_notices', json_encode($revoked), 'hub');
        Setting::set('hub_notices', json_encode(array_values($notices)), 'hub');

        $this->mailCritical();
    }

    /**
     * The notices this install should show now, by level.
     *
     * @return array<int, array<string, string>>
     */
    public function current(string $level): array
    {
        $revoked = $this->revoked();
        $dismissed = $level === 'info' ? $this->list('hub_dismissed_notices') : [];

        return array_values(array_filter($this->stored(), fn ($n) => $n['level'] === $level
            && ! in_array($n['id'], $revoked, true)
            && ! in_array($n['id'], $dismissed, true)
            && ! $this->expired($n)
            && $this->affectsThisInstall($n)));
    }

    /** An info notice can be closed; a critical one cannot. */
    public function dismiss(string $id): void
    {
        $notice = collect($this->stored())->firstWhere('id', $id);

        if (! $notice || $notice['level'] !== 'info') {
            return;
        }

        $dismissed = $this->list('hub_dismissed_notices');
        $dismissed[] = $id;
        Setting::set('hub_dismissed_notices', json_encode(array_values(array_unique($dismissed))), 'hub');
    }

    /**
     * The signed statement, decoded, or null when the signature does not
     * match the notice key (or there is no key).
     *
     * @return array<string, mixed>|null
     */
    public function verified(string $statement, string $signature): ?array
    {
        $pem = $this->publicKey ?? config('hub.notice_public_key');

        if (! is_string($pem) || trim($pem) === '') {
            return null;
        }

        $key = openssl_pkey_get_public($pem);
        $raw = base64_decode(trim($signature), true);

        if ($key === false || $raw === false || openssl_verify($statement, $raw, $key, OPENSSL_ALGO_SHA256) !== 1) {
            Log::warning('A hub notice was refused: its signature does not match the PNLCS notice key.');

            return null;
        }

        $data = json_decode($statement, true);

        return is_array($data) ? $data : null;
    }

    /** Only the PNLCS sites, and GitHub only under Panelica. */
    public static function allowedUrl(string $url): bool
    {
        // The same pattern the hub checks before it accepts a notice.
        if (! preg_match('#^https://(pnlcs\.com|docs\.pnlcs\.com|community\.pnlcs\.com|github\.com)(/[A-Za-z0-9._~/-]*)?$#', $url)) {
            return false;
        }

        $parts = parse_url($url);
        $host = $parts['host'] ?? null;
        $path = $parts['path'] ?? '';

        if (($parts['scheme'] ?? null) !== 'https' || ! in_array($host, self::URL_HOSTS, true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }

        return $host !== 'github.com' || (($segments[1] ?? null) === 'Panelica' && count($segments) > 2);
    }

    /**
     * A verified statement turned into a notice, or null if it breaks a rule.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    private function notice(array $data): ?array
    {
        $id = $data['id'] ?? null;
        $level = $data['level'] ?? null;
        $title = $data['title'] ?? null;
        $body = $data['body'] ?? '';
        $url = $data['url'] ?? '';
        $affects = $data['affects'] ?? '';

        if (! is_string($id) || ! $this->validId($id) || ! in_array($level, ['critical', 'info'], true)
            || ! is_string($title) || trim($title) === '' || mb_strlen($title) > 120
            || ! is_string($body) || mb_strlen($body) > 2000
            || ! is_string($url) || ($url !== '' && ! self::allowedUrl($url))
            || ! is_string($affects) || mb_strlen($affects) > 100) {
            return null;
        }

        $issued = $this->time($data['issued_at'] ?? null);
        $expires = $this->time($data['expires_at'] ?? null);

        if ($issued === null || $expires === null || $expires->lessThanOrEqualTo($issued)
            || $issued->diffInSeconds($expires) > self::MAX_DAYS * 86400) {
            return null;
        }

        $notice = [
            'id' => $id,
            'level' => $level,
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'affects' => $affects,
            'issued_at' => $issued->toIso8601ZuluString(),
            'expires_at' => $expires->toIso8601ZuluString(),
        ];

        return $this->expired($notice) ? null : $notice;
    }

    /**
     * Mail the admins about each critical notice once. Straight through Mail:
     * the notification rules are the operator's own and a new install has
     * none, so they would carry a security notice to nobody.
     */
    private function mailCritical(): void
    {
        $mailed = $this->list('hub_mailed_notices');
        $recipients = null;

        foreach ($this->current('critical') as $notice) {
            if (in_array($notice['id'], $mailed, true)) {
                continue;
            }

            $recipients ??= $this->recipients();

            if ($recipients === []) {
                Log::warning('A critical PNLCS notice could not be mailed: no admin address.', ['notice' => $notice['id']]);

                break;
            }

            try {
                Mail::raw($this->mailBody($notice), function ($message) use ($recipients, $notice) {
                    $message->to($recipients)->subject('[PNLCS] '.$notice['title']);
                });
            } catch (\Throwable $e) {
                Log::warning('A critical PNLCS notice could not be mailed.', ['notice' => $notice['id'], 'error' => $e->getMessage()]);

                continue;
            }

            $mailed[] = $notice['id'];
        }

        Setting::set('hub_mailed_notices', json_encode(array_values(array_unique($mailed))), 'hub');
    }

    /** @return array<int, string> */
    private function recipients(): array
    {
        $addresses = [(string) (Setting::get('SystemEmailAddress') ?: Setting::get('Email'))];

        $admins = Admin::query()
            ->where('is_disabled', false)
            ->whereHas('role', fn ($q) => $q->where('is_full_admin', true))
            ->pluck('email')
            ->all();

        return array_values(array_unique(array_filter(
            array_map(fn ($a) => strtolower(trim((string) $a)), array_merge($addresses, $admins)),
            fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL) !== false,
        )));
    }

    /** @param array<string, string> $notice */
    private function mailBody(array $notice): string
    {
        return implode("\n\n", array_filter([
            $notice['title'],
            $notice['body'],
            $notice['url'],
            __('admin.hub.mail_footer', ['url' => (string) config('app.url')]),
        ]));
    }

    /** @param array<string, string> $notice */
    private function affectsThisInstall(array $notice): bool
    {
        if ($notice['affects'] === '') {
            return true;
        }

        $version = app(Installation::class)->version();

        // A version that cannot be read is shown the notice: a security
        // notice missed is worse than one shown to an install it skips.
        return $version === null || $version->satisfies($notice['affects']);
    }

    /** @param array<string, string> $notice */
    private function expired(array $notice): bool
    {
        $expires = $this->time($notice['expires_at'] ?? null);

        return $expires === null || $expires->isPast();
    }

    private function time(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function validId(string $id): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9._-]{1,100}$/', $id);
    }

    /** @return array<string, array<string, string>> keyed by id */
    private function stored(): array
    {
        $notices = [];

        foreach ($this->list('hub_notices') as $notice) {
            if (is_array($notice) && isset($notice['id'], $notice['level'])) {
                $notices[$notice['id']] = $notice;
            }
        }

        return $notices;
    }

    /** @return array<int, string> */
    private function revoked(): array
    {
        return array_values(array_filter($this->list('hub_revoked_notices'), 'is_string'));
    }

    /** @return array<int, mixed> */
    private function list(string $setting): array
    {
        $value = json_decode((string) Setting::get($setting, '[]'), true);

        return is_array($value) ? array_values($value) : [];
    }
}
