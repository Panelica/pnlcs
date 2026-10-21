<?php

namespace Modules\Servers\PanelicaLicense;

use App\Models\ModuleQueue;
use App\Models\Server;
use App\Models\Service;
use App\Services\NotificationService;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Servers\AbstractServerModule;

/**
 * Panelica licenses, sold with a Panelica partner account.
 *
 * The partner account is set once, on the Panelica Licenses page in the
 * admin area (PartnerAccount: the partner API key, rk_..., kept encrypted).
 * There are no server records: nothing runs on a machine of the partner's.
 * A product's package is the Central plan it sells.
 *
 * HOW A LICENSE IS BOUGHT. Nothing is bought when the order is paid. The
 * customer gets an install command of their own, at this billing site's
 * address (/setup/<reference>). It runs Panelica's official installer through
 * the partner's install link with the order's reference, so when the new
 * panel starts its trial Central writes that trial to the partner and
 * remembers the reference. pnlcs:panelica-licenses then finds the server by
 * the reference (GET installs?ref=) and turns the trial into the plan the
 * customer bought (POST licenses/{id}/convert), charged to the partner's
 * balance. The customer never copies a key, and the partner never pays for
 * an order nobody installed.
 *
 * Each paid renewal buys the next period (renew()); suspending, unsuspending
 * and terminating the service do the same to the license.
 */
class PanelicaLicenseModule extends AbstractServerModule
{
    public const NAME = 'panelicalicense';

    /** PNLCS billing cycle => Central billing cycle. Central sells no other. */
    private const CYCLES = ['monthly' => 'monthly', 'annually' => 'yearly'];

    public function getModuleName(): string
    {
        return self::NAME;
    }

    public function getConfigFields(): array
    {
        return [];
    }

    // ------------------------------------------------------------------
    // Lifecycle
    // ------------------------------------------------------------------

    /**
     * Hand the customer their install link. Buys nothing: the license is
     * bought when their server shows up (checkInstall()).
     */
    public function create(Service $service): array
    {
        $data = $this->getModuleData($service);

        if (! empty($data['ended'])) {
            return $this->buildResult(false, __('panelica_license.error.ended'));
        }

        // Already bought: Create is not a way to buy a second one.
        if (! empty($data['license_id'])) {
            return $this->buildResult(true, __('panelica_license.result.already_licensed'));
        }

        $client = CentralClient::partner();
        if (! $client) {
            return $this->buildResult(false, __('panelica_license.error.no_account'));
        }

        if (! $this->planId($service)) {
            return $this->buildResult(false, __('panelica_license.error.no_plan'));
        }

        if (! $this->centralCycle($service)) {
            return $this->buildResult(false, __('panelica_license.error.cycle', ['cycle' => (string) $service->billing_cycle]));
        }

        // The command must work when the customer runs it, so the partner
        // account and its install link are asked about now; a refusal here
        // goes to the retry queue instead of handing out a dead command.
        $link = $client->get('install-link');
        if (! $link->ok || blank($link->data['code'] ?? null)) {
            return $this->buildResult(false, __('panelica_license.error.central', ['error' => $link->error ?: 'no install link']));
        }

        $fields = ['last_error' => null];
        if (empty($data['install_ref'])) {
            $fields += ['install_ref' => self::newReference(), 'install_ref_at' => now()->toIso8601String()];
        }
        $this->setModuleData($service, $fields);

        $result = $this->buildResult(true, __('panelica_license.result.waiting'));
        $this->logAction($service, 'create', $result);

        return $result;
    }

    public function suspend(Service $service, string $reason = ''): array
    {
        $data = $this->getModuleData($service);

        // Nothing bought yet: the link stops working while the service is not
        // active (see SetupController), so there is nothing else to pause.
        if (empty($data['license_id'])) {
            return $this->buildResult(true, __('panelica_license.result.nothing_bought'));
        }

        $client = CentralClient::partner();
        if (! $client) {
            return $this->buildResult(false, __('panelica_license.error.no_account'));
        }

        $response = $client->patch('licenses/'.$data['license_id'].'/suspend', [
            'reason' => Str::limit('Suspended in billing'.($reason !== '' ? ': '.$reason : ''), 250, ''),
        ]);

        // Already suspended - by Panelica, or by us before - is suspended.
        if (! $response->ok && $response->status !== 409) {
            return $this->buildResult(false, __('panelica_license.error.central', ['error' => $response->error]));
        }

        $this->setModuleData($service, [
            'license_status' => 'suspended',
            // Only what we suspended is ours to lift (Central lifts a
            // partner's own suspension only).
            'suspended_here' => $response->ok || ! empty($data['suspended_here']),
        ]);

        $result = $this->buildResult(true, __('panelica_license.result.suspended'));
        $this->logAction($service, 'suspend', $result);

        return $result;
    }

    public function unsuspend(Service $service): array
    {
        $data = $this->getModuleData($service);

        if (! empty($data['ended'])) {
            return $this->buildResult(false, __('panelica_license.error.ended'));
        }

        if (empty($data['license_id'])) {
            return $this->buildResult(true, __('panelica_license.result.nothing_bought'));
        }

        $client = CentralClient::partner();
        if (! $client) {
            return $this->buildResult(false, __('panelica_license.error.no_account'));
        }

        $license = $client->get('licenses/'.$data['license_id']);
        if (! $license->ok) {
            return $this->buildResult(false, __('panelica_license.error.central', ['error' => $license->error]));
        }

        // A paid renewal lifts a suspension for non-payment by itself, and it
        // runs as soon as the invoice is paid - before this does.
        if (($license->data['status'] ?? '') !== 'suspended') {
            $this->storeLicense($service, (array) $license->data, ['suspended_here' => false]);

            return $this->buildResult(true, __('panelica_license.result.active'));
        }

        $activated = $client->patch('licenses/'.$data['license_id'].'/activate');
        if (! $activated->ok) {
            return $this->buildResult(false, __('panelica_license.error.central', ['error' => $activated->error]));
        }

        $this->setModuleData($service, ['license_status' => 'active', 'suspended_here' => false]);

        $result = $this->buildResult(true, __('panelica_license.result.active'));
        $this->logAction($service, 'unsuspend', $result);

        return $result;
    }

    /**
     * End the license: it is suspended at Central and never renewed again,
     * so it stops working now and lapses at the end of the period it has.
     * The install link dies with the service.
     */
    public function terminate(Service $service): array
    {
        $data = $this->getModuleData($service);

        if (! empty($data['license_id']) && empty($data['ended'])) {
            $client = CentralClient::partner();
            if (! $client) {
                return $this->buildResult(false, __('panelica_license.error.no_account'));
            }

            $response = $client->patch('licenses/'.$data['license_id'].'/suspend', [
                'reason' => 'Service terminated in billing',
            ]);

            if (! $response->ok && $response->status !== 409) {
                return $this->buildResult(false, __('panelica_license.error.central', ['error' => $response->error]));
            }

            $data['license_status'] = 'suspended';
        }

        $this->setModuleData($service, ['ended' => true, 'license_status' => $data['license_status'] ?? null]);

        $result = $this->buildResult(true, __('panelica_license.result.ended'));
        $this->logAction($service, 'terminate', $result);

        return $result;
    }

    public function changePassword(Service $service, string $newPassword): array
    {
        return $this->buildResult(false, __('panelica_license.error.no_password'));
    }

    /**
     * Move the license to the new product's plan. Before the license is
     * bought there is nothing to move: it is bought on whatever plan the
     * service's product names at the time.
     */
    public function changePackage(Service $service, array $newPackage): array
    {
        $config = is_string($newPackage['config_options'] ?? null)
            ? json_decode($newPackage['config_options'], true)
            : ($newPackage['config_options'] ?? []);
        $planId = (string) ($config['package_name'] ?? '');

        if ($planId === '') {
            return $this->buildResult(false, __('panelica_license.error.no_plan'));
        }

        $data = $this->getModuleData($service);
        if (empty($data['license_id'])) {
            return $this->buildResult(true, __('panelica_license.result.plan_later'));
        }

        $client = CentralClient::partner();
        if (! $client) {
            return $this->buildResult(false, __('panelica_license.error.no_account'));
        }

        $response = $client->post(
            'licenses/'.$data['license_id'].'/change-plan',
            ['plan_id' => $planId, 'timing' => 'now'],
            $this->idempotencyKey($service, 'plan-'.$planId.'-'.now()->format('YmdH'))
        );

        // A paid upgrade moves the service to the new product whatever this
        // answers (UpgradeService: billing is authoritative), so a license
        // left on the old plan is told to staff, not only logged.
        if (! $response->ok) {
            return $this->failed($service, __('panelica_license.error.plan_change', ['error' => $response->error]));
        }

        $this->refreshLicense($service);

        $result = $this->buildResult(true, __('panelica_license.result.plan_changed'));
        $this->logAction($service, 'changePackage', $result);

        return $result;
    }

    /**
     * Buy the period the customer has just paid for.
     *
     * The key carries the service's new due date, so the same paid period is
     * bought once however often this runs (the retry queue, a double event),
     * and the next period is a new purchase.
     */
    public function renew(Service $service): array
    {
        $data = $this->getModuleData($service);

        if (! empty($data['ended'])) {
            return $this->buildResult(true, __('panelica_license.result.ended'));
        }

        // Nothing bought yet: the license is bought when the server shows up,
        // for a period that starts then.
        if (empty($data['license_id'])) {
            return $this->buildResult(true, __('panelica_license.result.nothing_bought'));
        }

        $client = CentralClient::partner();
        if (! $client) {
            return $this->buildResult(false, __('panelica_license.error.no_account'));
        }

        // Overdue services are suspended here, and so their license at
        // Central; Central renews only an active, expired or non-payment
        // suspended license, and the unsuspend that follows payment runs up to
        // half an hour after this. A suspension we placed is lifted first.
        if (! empty($data['suspended_here'])) {
            $license = $client->get('licenses/'.$data['license_id']);
            if ($license->ok && ($license->data['status'] ?? '') === 'suspended') {
                $activated = $client->patch('licenses/'.$data['license_id'].'/activate');
                if (! $activated->ok && $activated->status !== 409) {
                    return $this->buildResult(false, __('panelica_license.error.central', ['error' => $activated->error]));
                }
            }
        }

        $due = $service->fresh()?->next_due_date?->toDateString() ?? now()->toDateString();
        $response = $client->post('licenses/'.$data['license_id'].'/renew', [], $this->idempotencyKey($service, 'renew-'.$due));

        if (! $response->ok) {
            return $this->buildResult(false, $response->isBalanceShort()
                ? __('panelica_license.error.balance', ['error' => $response->error])
                : __('panelica_license.error.central', ['error' => $response->error]));
        }

        $this->setModuleData($service, [
            'license_status' => (string) ($response->data['status'] ?? 'active'),
            'license_expires_at' => $response->data['expires_at'] ?? ($data['license_expires_at'] ?? null),
            'suspended_here' => false,
            'last_error' => null,
        ]);

        $result = $this->buildResult(true, __('panelica_license.result.renewed'));
        $this->logAction($service, 'renew', $result);

        return $result;
    }

    // ------------------------------------------------------------------
    // Finding the customer's server and buying its license
    // ------------------------------------------------------------------

    /**
     * Look for the server installed with this service's link and, once its
     * panel has started a trial that Central wrote to the partner, buy the
     * plan the customer paid for. Safe to run any number of times: the
     * purchase carries an idempotency key, and a license already bought is
     * only read back.
     */
    public function checkInstall(Service $service): array
    {
        $data = $this->getModuleData($service);

        if (! empty($data['license_id'])) {
            return $this->refreshLicense($service);
        }

        if (! empty($data['ended']) || empty($data['install_ref'])) {
            return $this->buildResult(false, __('panelica_license.error.no_link'));
        }

        $client = CentralClient::partner();
        if (! $client) {
            return $this->buildResult(false, __('panelica_license.error.no_account'));
        }

        $found = $client->get('installs', ['ref' => $data['install_ref'], 'per_page' => 20]);
        if (! $found->ok) {
            return $this->failed($service, __('panelica_license.error.central', ['error' => $found->error]));
        }

        // Only this order's servers. A Central that does not know the ref
        // filter answers with every install of the partner, and taking one of
        // those would buy a license for someone else's server on this order.
        $installs = array_values(array_filter(
            (array) $found->data,
            fn ($install) => is_array($install) && ($install['ref'] ?? null) === $data['install_ref']
        ));
        $seen = ['checked_at' => now()->toIso8601String(), 'installs_seen' => count($installs)];

        if ($installs === []) {
            $this->setModuleData($service, $seen + ['last_error' => null]);

            return $this->buildResult(true, __('panelica_license.result.not_seen'));
        }

        $linked = array_values(array_filter($installs, fn ($i) => ($i['status'] ?? '') === 'linked' && ! empty($i['license']['id'])));
        // The first server that ran this order's link gets its license; any
        // other one stays a trial (the partner sees them in Central).
        usort($linked, fn ($a, $b) => strcmp((string) ($a['matched_at'] ?? ''), (string) ($b['matched_at'] ?? '')));
        $install = $linked[0] ?? $installs[0];
        $seen['install'] = self::installSnapshot($install);

        if (! $linked) {
            $this->setModuleData($service, $seen);

            // Central's note says why the server cannot be tied to the order
            // (the trial was refused, the server is licensed already ...). It
            // is written for the partner, so it goes to staff; the customer is
            // told someone is on it.
            $note = trim((string) ($install['note'] ?? ''));
            if ($note !== '') {
                return $this->failed($service, __('panelica_license.error.install_note', ['note' => $note]));
            }

            $this->setModuleData($service, ['last_error' => null]);

            return $this->buildResult(true, __('panelica_license.result.seen'));
        }

        $license = (array) $install['license'];

        if (empty($license['convertible'])) {
            // Bought already (a response that never reached us, or the
            // partner did it by hand): take it over.
            if (! empty($install['converted_at'])) {
                $this->setModuleData($service, $seen + ['license_id' => (string) $license['id']]);

                return $this->refreshLicense($service, ['licensed_at' => (string) $install['converted_at']]);
            }

            $this->setModuleData($service, $seen);

            return $this->failed($service, __('panelica_license.error.not_convertible', ['status' => (string) ($license['status'] ?? '?')]));
        }

        // A product set up "as soon as the order is placed" hands out the
        // link before payment; the partner pays only for a paid order.
        if (! $this->orderPaid($service)) {
            $this->setModuleData($service, $seen + ['last_error' => null]);

            return $this->buildResult(true, __('panelica_license.result.unpaid'));
        }

        $converted = $client->post('licenses/'.$license['id'].'/convert', [
            'plan_id' => $this->planId($service),
            'billing_cycle' => $this->centralCycle($service),
        ], $this->idempotencyKey($service, 'convert-'.$license['id']));

        $this->setModuleData($service, $seen);

        if (! $converted->ok) {
            return $this->failed($service, $converted->isBalanceShort()
                ? __('panelica_license.error.balance', ['error' => $converted->error])
                : __('panelica_license.error.central', ['error' => $converted->error]));
        }

        $this->storeLicense($service, (array) $converted->data + [
            'server_ip' => $install['ip_address'] ?? null,
            'server_hostname' => $install['hostname'] ?? null,
        ], ['licensed_at' => now()->toIso8601String(), 'last_error' => null]);

        // A license that came from a refreshed read carries everything; this
        // fills in the server and the last check-in.
        $this->refreshLicense($service);

        $result = $this->buildResult(true, __('panelica_license.result.licensed'));
        $this->logAction($service, 'license', $result);

        return $result;
    }

    /** Read the license back from Central. */
    public function refreshLicense(Service $service, array $extra = []): array
    {
        $data = $this->getModuleData($service);
        $client = CentralClient::partner();
        if (empty($data['license_id']) || ! $client) {
            return $this->buildResult(false, __('panelica_license.error.no_account'));
        }

        $license = $client->get('licenses/'.$data['license_id']);
        if (! $license->ok) {
            return $this->failed($service, __('panelica_license.error.central', ['error' => $license->error]));
        }

        $this->storeLicense($service, (array) $license->data, $extra + ['checked_at' => now()->toIso8601String(), 'last_error' => null]);

        return $this->buildResult(true, __('panelica_license.result.refreshed'));
    }

    /**
     * Staff: buy the paid period the retry queue is still waiting on, now.
     * Offered only while a renewal waits (a short partner balance, Central
     * away), and keyed like the queue's own attempt, so it cannot buy a
     * period twice.
     */
    public function retryRenewal(Service $service): array
    {
        $entry = $this->waitingRenewal($service);
        if (! $entry) {
            return $this->buildResult(false, __('panelica_license.error.no_renewal_waiting'));
        }

        $result = $this->renew($service);

        $entry->update(($result['success'] ?? false)
            ? ['status' => 'completed', 'completed_at' => now(), 'last_error' => null]
            : ['last_error' => (string) ($result['message'] ?? '')]);

        return $result;
    }

    /** A paid period the retry queue has not managed to buy yet. */
    public function waitingRenewal(Service $service): ?ModuleQueue
    {
        return ModuleQueue::where('service_id', $service->id)
            ->where('action', 'renew')
            ->whereIn('status', ['pending', 'failed'])
            ->latest('id')
            ->first();
    }

    /**
     * Tie a license that already exists at Central to this service (bought by
     * hand in the partner portal, or one the link could not reach). The
     * license must belong to this partner account. From then on the service
     * renews, suspends and ends it like one it bought itself.
     */
    public function attachLicense(Service $service, string $id): array
    {
        $id = trim($id);
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $id)) {
            return $this->buildResult(false, __('panelica_license.error.license_id'));
        }

        $data = $this->getModuleData($service);
        if (! empty($data['license_id']) || ! empty($data['ended'])) {
            return $this->buildResult(false, __('panelica_license.error.link_fixed'));
        }

        $client = CentralClient::partner();
        if (! $client) {
            return $this->buildResult(false, __('panelica_license.error.no_account'));
        }

        $license = $client->get('licenses/'.$id);
        if (! $license->ok) {
            return $this->buildResult(false, __('panelica_license.error.central', ['error' => $license->error]));
        }

        $this->storeLicense($service, (array) $license->data, [
            'licensed_at' => now()->toIso8601String(),
            'checked_at' => now()->toIso8601String(),
            'last_error' => null,
        ]);

        $result = $this->buildResult(true, __('panelica_license.result.attached'));
        $this->logAction($service, 'attachLicense', $result);

        return $result;
    }

    /**
     * A new link for the customer; the old one stops working here at once.
     * Only before the license is bought: after that the link has done its job.
     */
    public function newInstallLink(Service $service): array
    {
        $data = $this->getModuleData($service);
        if (! empty($data['license_id']) || ! empty($data['ended']) || empty($data['install_ref'])) {
            return $this->buildResult(false, __('panelica_license.error.link_fixed'));
        }

        $this->setModuleData($service, [
            'install_ref' => self::newReference(),
            'install_ref_at' => now()->toIso8601String(),
            'script_fetched_at' => null,
            'install' => null,
            'installs_seen' => 0,
        ]);

        return $this->buildResult(true, __('panelica_license.result.new_link'));
    }

    // ------------------------------------------------------------------
    // Screens, mail and buttons (see App\Services\Module\ModuleScreens)
    // ------------------------------------------------------------------

    public function servicePageHides(): array
    {
        return ['server_details', 'change_password'];
    }

    public function isProvisioned(Service $service): bool
    {
        $data = $this->getModuleData($service);

        return ! empty($data['license_id']) || ! empty($data['install_ref']) || ! empty($data['ended']);
    }

    /** Staff buttons. Also what the API's modulecustom may run. */
    public function customFunctions(?Service $service = null): array
    {
        $functions = ['checkNow' => __('panelica_license.action.check_now')];

        if (! $service || $this->linkCanChange($service)) {
            $functions['newInstallLink'] = __('panelica_license.action.new_link');
        }

        if (! $service || $this->waitingRenewal($service)) {
            $functions['retryRenewal'] = __('panelica_license.action.retry_renewal');
        }

        return $functions;
    }

    /** The customer may look again and replace a link they shared by mistake. */
    public function clientFunctions(?Service $service = null): array
    {
        if ($service && strtolower((string) $service->status) !== 'active') {
            return [];
        }

        $functions = ['checkNow' => __('panelica_license.action.check_now')];

        if (! $service || $this->linkCanChange($service)) {
            $functions['newInstallLink'] = __('panelica_license.action.new_link');
        }

        return $functions;
    }

    public function checkNow(Service $service): array
    {
        return $this->checkInstall($service);
    }

    public function emailMergeFields(Service $service): array
    {
        $data = $this->getModuleData($service);

        return [
            'service_install_command' => $this->installCommand($service) ?? '',
            'service_license_key' => (string) ($data['license_key'] ?? ''),
        ];
    }

    public function clientAreaPanel(Service $service): ?Htmlable
    {
        return view('client.services.partials.panelica-license', $this->screenData($service));
    }

    public function adminAreaPanel(Service $service): ?Htmlable
    {
        return view('admin.services.partials.panelica-license', $this->screenData($service));
    }

    /** What both screens show. */
    public function screenData(Service $service): array
    {
        $data = $this->getModuleData($service);

        $state = match (true) {
            ! empty($data['ended']) => 'ended',
            ! empty($data['license_id']) => 'licensed',
            ! empty($data['install_ref']) => 'waiting',
            default => 'none',
        };

        return [
            'service' => $service,
            'state' => $state,
            'data' => $data,
            'command' => $state === 'waiting' ? $this->installCommand($service) : null,
            'install' => is_array($data['install'] ?? null) ? $data['install'] : null,
            'canChangeLink' => $this->linkCanChange($service),
            'waitingRenewal' => $this->waitingRenewal($service),
        ];
    }

    public function installCommand(Service $service): ?string
    {
        $ref = $this->getModuleData($service)['install_ref'] ?? null;

        return is_string($ref) && $ref !== ''
            ? 'curl -sSL '.route('panelica-license.setup', $ref).' | bash'
            : null;
    }

    // ------------------------------------------------------------------
    // Partner account (the Panelica Licenses page, not a server record)
    // ------------------------------------------------------------------

    /**
     * Nothing runs on a machine of the partner's: the account is set once on
     * the Panelica Licenses page, so this module has no server records and
     * the product form asks it for its plans directly.
     */
    public function needsServerRecord(): bool
    {
        return false;
    }

    /**
     * The Central plans, for the product form; the partner's own price beside
     * each so the selling price can be set with the margin in view.
     */
    public function listPackages(?Server $server = null): array
    {
        $client = CentralClient::partner();
        if (! $client) {
            throw new \RuntimeException(__('panelica_license.error.no_account'));
        }

        $answer = $client->get('plans');
        if (! $answer->ok) {
            throw new \RuntimeException(__('panelica_license.error.central', ['error' => $answer->error]));
        }

        $plans = array_values(array_filter((array) $answer->data, fn ($plan) => is_array($plan) && ! empty($plan['id'])));

        return array_map(fn (array $plan) => [
            'id' => (string) $plan['id'],
            'name' => __('panelica_license.plan_option', [
                'name' => (string) ($plan['name'] ?? $plan['id']),
                'monthly' => number_format((float) ($plan['reseller_price_monthly'] ?? 0), 2),
                'yearly' => number_format((float) ($plan['reseller_price_yearly'] ?? 0), 2),
            ]),
        ], $plans);
    }

    /** @return list<array<string, mixed>> Central's plans, as it lists them */
    public function plans(): array
    {
        $plans = CentralClient::partner()?->get('plans');
        if (! $plans || ! $plans->ok) {
            return [];
        }

        return array_values(array_filter((array) $plans->data, fn ($plan) => is_array($plan) && ! empty($plan['id'])));
    }

    /** No server records: licenses are read back by pnlcs:panelica-licenses. */
    public function usageUpdate(Server $server): array
    {
        return ['updated' => 0, 'errors' => 0];
    }

    public function testConnection(?Server $server = null): bool
    {
        $me = CentralClient::partner()?->get('me');

        return $me && $me->ok && in_array((string) ($me->data['status'] ?? ''), ['approved', 'active'], true);
    }

    // ------------------------------------------------------------------

    public static function newReference(): string
    {
        return Str::random(32);
    }

    private function linkCanChange(Service $service): bool
    {
        $data = $this->getModuleData($service);

        return empty($data['license_id']) && empty($data['ended']) && ! empty($data['install_ref']);
    }

    /**
     * Whether the order this service came from is paid. A service staff
     * added by hand has no order and is theirs to answer for.
     */
    private function orderPaid(Service $service): bool
    {
        $invoice = $service->order?->invoice;

        return ! $invoice || strtolower((string) $invoice->status) === 'paid';
    }

    private function planId(Service $service): ?string
    {
        $plan = $this->getRemotePackage($service);

        return is_string($plan) && $plan !== '' ? $plan : null;
    }

    private function centralCycle(Service $service): ?string
    {
        return self::CYCLES[strtolower(str_replace(['-', ' '], '', (string) $service->billing_cycle))] ?? null;
    }

    private function idempotencyKey(Service $service, string $what): string
    {
        $site = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'pnlcs';

        return Str::limit('pnlcs-'.$site.'-svc-'.$service->id.'-'.$what, 250, '');
    }

    private function storeLicense(Service $service, array $license, array $extra = []): void
    {
        $plan = $license['plan'] ?? null;

        $this->setModuleData($service, array_filter([
            'license_id' => isset($license['id']) ? (string) $license['id'] : null,
            'license_key' => $license['license_key'] ?? null,
            'license_status' => $license['status'] ?? null,
            'license_plan' => is_array($plan) ? ($plan['name'] ?? null) : (is_string($plan) ? $plan : null),
            'license_cycle' => $license['billing_cycle'] ?? null,
            'license_expires_at' => $license['expires_at'] ?? null,
            'license_server_ip' => $license['server_ip'] ?? null,
            'license_server_hostname' => $license['server_hostname'] ?? null,
            'license_heartbeat_at' => $license['last_heartbeat_at'] ?? null,
        ], fn ($value) => $value !== null) + $extra);
    }

    /**
     * Remember a problem for the screens, and tell staff once per problem: the
     * job runs every five minutes, and the same alert every five minutes is
     * noise nobody reads.
     */
    private function failed(Service $service, string $message): array
    {
        $previous = $this->getModuleData($service)['last_error'] ?? null;
        $this->setModuleData($service, ['last_error' => $message, 'last_error_at' => now()->toIso8601String()]);

        if ($previous !== $message) {
            Log::warning("Panelica license: service #{$service->id}: {$message}");
            run_hook('ModuleActionFailed', ['service' => $service, 'action' => 'license', 'error' => $message]);

            try {
                app(NotificationService::class)->dispatch('module.failed', [
                    'event_type' => 'module.failed',
                    'subject' => 'Panelica license needs attention',
                    'message' => "Service #{$service->id}: {$message}",
                    'service_id' => $service->id,
                    'action' => 'license',
                ]);
            } catch (\Throwable $e) {
                Log::warning('Panelica license: could not notify staff', ['error' => $e->getMessage()]);
            }
        }

        return $this->buildResult(false, $message);
    }

    private static function installSnapshot(array $install): array
    {
        return [
            'id' => $install['id'] ?? null,
            'hostname' => $install['hostname'] ?? null,
            'ip_address' => $install['ip_address'] ?? null,
            'os' => $install['os'] ?? null,
            'status' => $install['status'] ?? null,
            'note' => $install['note'] ?? null,
            'reported_at' => $install['reported_at'] ?? null,
        ];
    }
}
