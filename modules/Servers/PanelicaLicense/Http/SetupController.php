<?php

namespace Modules\Servers\PanelicaLicense\Http;

use App\Models\Service;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Modules\Servers\PanelicaLicense\CentralClient;
use Modules\Servers\PanelicaLicense\PanelicaLicenseModule;

/**
 * GET /setup/{reference}: the customer's own install command.
 *
 *   curl -sSL https://<this billing site>/setup/<reference> | bash
 *
 * Answers with the script Central serves at /install/<partner code>/<reference>,
 * fetched here, so the command the customer sees carries this site's address
 * and nothing else. The script runs Panelica's official installer unchanged and
 * tells Central which order the server belongs to.
 *
 * Anything that cannot be tied to an order that is waiting for its server is
 * answered with a script that says so and stops, rather than an installer
 * nobody could connect to the order afterwards.
 */
class SetupController
{
    public function __invoke(string $reference): Response
    {
        $service = $this->serviceFor($reference);

        if (! $service) {
            return $this->refuse(__('panelica_license.script.unknown'));
        }

        // The customer's own language: this runs with no session.
        $locale = (string) ($service->client?->language ?? '');
        if ($locale !== '' && is_dir(lang_path($locale))) {
            app()->setLocale($locale);
        }

        $data = $service->module_data ?? [];

        if (! empty($data['ended']) || strtolower((string) $service->status) !== 'active') {
            return $this->refuse(__('panelica_license.script.not_active'));
        }

        if (! empty($data['license_id'])) {
            return $this->refuse(__('panelica_license.script.used'));
        }

        $server = $service->server;
        $client = $server ? CentralClient::for($server) : null;
        $code = $client ? $this->partnerCode($server->id, $client) : null;
        $script = $code ? $client->installScript($code, $reference) : null;

        if ($script === null) {
            return $this->refuse(__('panelica_license.script.unavailable'));
        }

        $service->forceFill(['module_data' => array_merge($data, ['script_fetched_at' => now()->toIso8601String()])])->save();

        return new Response($script, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function serviceFor(string $reference): ?Service
    {
        return Service::with('server', 'client')
            ->where('module_data->install_ref', $reference)
            ->whereHas('server', fn ($q) => $q->where('type', PanelicaLicenseModule::NAME))
            ->first();
    }

    /**
     * The partner's install link code. Kept for ten minutes so a run of
     * installs does not ask Central each time; a failure is not kept.
     */
    private function partnerCode(int $serverId, CentralClient $client): ?string
    {
        $key = 'panelica_license:install_code:'.$serverId;

        if ($cached = Cache::get($key)) {
            return (string) $cached;
        }

        $link = $client->get('install-link');
        $code = $link->ok ? (string) ($link->data['code'] ?? '') : '';
        if ($code === '') {
            return null;
        }

        Cache::put($key, $code, now()->addMinutes(10));

        return $code;
    }

    /** A script that prints why and stops: piped into bash, it must still be a script. */
    private function refuse(string $message): Response
    {
        $quoted = "'".str_replace("'", "'\\''", $message)."'";

        return new Response("#!/usr/bin/env bash\nprintf '%s\\n' {$quoted} >&2\nexit 1\n", 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
