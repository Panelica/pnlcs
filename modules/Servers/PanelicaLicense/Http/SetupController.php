<?php

namespace Modules\Servers\PanelicaLicense\Http;

use App\Models\Service;
use Illuminate\Http\Response;
use Modules\Servers\PanelicaLicense\CentralClient;
use Modules\Servers\PanelicaLicense\PanelicaLicenseModule;
use Modules\Servers\PanelicaLicense\PartnerAccount;

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

        if (! PanelicaLicenseModule::enabled() || ! empty($data['ended']) || strtolower((string) $service->status) !== 'active') {
            return $this->refuse(__('panelica_license.script.not_active'));
        }

        if (! empty($data['license_id'])) {
            return $this->refuse(__('panelica_license.script.used'));
        }

        $client = CentralClient::partner();
        $code = $client ? PartnerAccount::installCode($client) : null;
        $script = $code ? $client->installScript($code, $reference) : null;

        if ($script === null) {
            return $this->refuse(__('panelica_license.script.unavailable'));
        }

        $service->forceFill(['module_data' => array_merge($data, ['script_fetched_at' => now()->toIso8601String()])])->save();

        return $this->script($script);
    }

    /**
     * GET /setup: the partner's general install link at this site's address,
     * for the partner to publish. A server installed with it is the partner's
     * (its trial goes to the partner account) but belongs to no order: it
     * shows on the Panelica Licenses page, and can be tied to a service there.
     */
    public function general(): Response
    {
        $client = PanelicaLicenseModule::enabled() ? CentralClient::partner() : null;
        $code = $client ? PartnerAccount::installCode($client) : null;
        $script = $code ? $client->installScript($code) : null;

        return $script === null
            ? $this->refuse(__('panelica_license.script.unavailable'))
            : $this->script($script);
    }

    private function script(string $script): Response
    {
        return new Response($script, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function serviceFor(string $reference): ?Service
    {
        return Service::with('client')
            ->where('module_data->install_ref', $reference)
            ->whereHas('product', fn ($q) => $q->where('server_type', PanelicaLicenseModule::NAME))
            ->first();
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
