<?php

namespace App\Http\Controllers\Client;

use App\Contracts\ServerModuleInterface;
use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Mail\CancellationConfirmMail;
use App\Models\CancellationRequest;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\AddonService;
use App\Services\ProvisioningService;
use App\Services\UpgradeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ServiceController extends Controller
{
    use ResolvesClient;

    public function index()
    {
        $services = Service::with('product')
            ->where('client_id', $this->getClientId())
            ->orderBy('id', 'desc')
            ->paginate(25);

        return view('client.services.index', compact('services'));
    }

    public function show(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $service->load('product', 'server', 'addons.addon');

        $availableAddons = $service->product
            ? app(AddonService::class)->availableFor($service->product)
                ->reject(fn ($addon) => $service->addons
                    ->where('addon_id', $addon->id)
                    ->whereIn('status', ['active', 'pending'])
                    ->isNotEmpty())
                ->values()
            : collect();

        // Hosting self-service tabs this service offers (Panelica-only today).
        // Resolved from the module so a different server type simply returns [].
        $hostingFeatures = $service->hostingFeatureKeys();

        return view('client.services.show', compact('service', 'availableAddons', 'hostingFeatures'));
    }

    /** Order an addon for a running service; it starts once its invoice is paid. */
    public function storeAddon(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $request->validate(['addon_id' => 'required|integer|exists:product_addons,id']);

        $result = app(AddonService::class)->purchaseForService(
            $service,
            ProductAddon::findOrFail($request->integer('addon_id'))
        );

        return redirect()->route('client.invoices.show', $result['invoice'])
            ->with('success', __('client.services.addon_ordered'));
    }

    /** Stop an addon without disturbing the service. */
    public function cancelAddon(Service $service, ServiceAddon $addon)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        abort_if($addon->service_id !== $service->id, 404);

        app(AddonService::class)->cancel($addon);

        return back()->with('success', __('client.services.addon_cancelled'));
    }

    /**
     * Live resource usage (disk / bandwidth / counts) for the client usage graphs.
     */
    public function usage(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        // Reading is not a reason to choose a server. Asking a module for one
        // makes it pick and write it onto the service, so a customer opening
        // this page used to nail an order that was never provisioned to
        // whichever server the module happened to return.
        if (! $service->server_id) {
            return response()->json(['available' => false]);
        }

        $module = app(ProvisioningService::class)->resolveModule($service);
        if (! $module || ! method_exists($module, 'liveUsage')) {
            return response()->json(['available' => false]);
        }

        return response()->json($module->liveUsage($service));
    }

    /**
     * Single sign-on into the hosting control panel for this service.
     */
    public function loginToPanel(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        // Cancelled, terminated, marked fraud: the customer may no longer act
        // on this service. The cancellation form has always asked; this did
        // not, and the button being hidden is no answer - the route answers
        // whoever calls it, and a customer who kept the URL still has it.
        if (! $this->isLive($service)) {
            return back()->with('error', __('client.services.not_live_for_action'));
        }

        // No server yet means there is no account to sign in to; asking the
        // module would pick a server and bind the service to it.
        if (! $service->server_id) {
            return back()->with('error', __('messages.error.panel_login_unavailable'));
        }

        $module = app(ProvisioningService::class)->resolveModule($service);
        if (! $module || ! method_exists($module, 'ssoLogin')) {
            return back()->with('error', __('messages.error.panel_login_unavailable'));
        }

        $result = $module->ssoLogin($service);
        if (($result['success'] ?? false) && ! empty($result['data']['url'])) {
            return redirect()->away($result['data']['url']);
        }

        return back()->with('error', $result['message'] ?? __('messages.error.panel_login_unavailable'));
    }

    public function requestCancellation(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        return view('client.services.cancel', compact('service'));
    }

    public function submitCancellation(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $validated = $request->validate([
            'type' => 'required|in:Immediate,End of Billing Period',
            'reason' => 'required|string|max:1000',
        ]);

        if (! $this->isLive($service)) {
            return back()->with('error', __('client.services.not_live_for_action'));
        }

        // A second request would send another confirmation and give the
        // cancellation cron two rows for the same service.
        // Only one open request at a time. It used to be one ever: a
        // request that had already been acted on blocked the customer from
        // asking again for the rest of the service's life.
        if (CancellationRequest::where('service_id', $service->id)->whereNull('processed_at')->exists()) {
            return back()->with('error', __('client.services.cancellation_already_requested'));
        }

        CancellationRequest::create([
            'service_id' => $service->id,
            'type' => $validated['type'],
            'reason' => $validated['reason'],
        ]);

        $service->load('client');
        if ($service->client?->email) {
            Mail::to($service->client->email)->queue(new CancellationConfirmMail($service, $validated['type']));
        }

        return redirect()->route('client.services.show', $service)
            ->with('success', __('messages.success.cancellation_request_submitted'));
    }

    public function upgrade(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $service->load('product');

        // r127-cycle: the customer's own term decides both what is on offer and
        // what it costs. The screen used to list every active package and work
        // the price out in the view by taking the first cycle with a figure in
        // it - monthly, almost always - so somebody on an annual service was
        // shown monthly prices, and packages not sold annually at all were
        // offered to them and refused after they had chosen one.
        $cycle = $service->billing_cycle ?: 'Monthly';

        $prices = [];

        $availableProducts = Product::active()
            ->where('id', '!=', $service->product_id)
            ->with('pricing')
            ->get()
            ->filter(function (Product $product) use ($cycle, &$prices) {
                $price = $product->priceFor($cycle);

                if ($price === null) {
                    return false;
                }

                $prices[$product->id] = $price;

                return true;
            })
            ->values();

        return view('client.services.upgrade', [
            'service' => $service,
            'upgrades' => $availableProducts,
            'upgradePrices' => $prices,
            'upgradeCycle' => $cycle,
        ]);
    }

    public function processUpgrade(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $validated = $request->validate([
            'new_product_id' => 'required|exists:products,id',
        ]);

        $newProduct = Product::with('pricing')->findOrFail($validated['new_product_id']);

        $result = app(UpgradeService::class)->requestProductChange($service, $newProduct);

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        if ($result['invoice']) {
            return redirect()->route('client.invoices.show', $result['invoice'])
                ->with('success', __('messages.success.upgrade_invoice_created'));
        }

        return redirect()->route('client.services.show', $service->fresh())
            ->with('success', __('messages.success.package_changed'));
    }

    public function toggleAutoRenew(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $service->update([
            'auto_renew' => ! $service->auto_renew,
        ]);

        $status = $service->auto_renew ? 'enabled' : 'disabled';

        return back()->with('success', __('messages.success.auto_renewal_toggled', ['status' => $status, 'domain' => $service->domain]));
    }

    /** A service that has ended cannot be upgraded, cancelled or renewed. */
    private function isLive(Service $service): bool
    {
        return ! in_array(strtolower((string) $service->status), ['terminated', 'cancelled', 'fraud'], true);
    }

    // -------------------------------------------------------------------------
    // Hosting management (Panelica-only, feature-gated)
    //
    // The whole hosting area is silent unless the resolved server module both
    // lists the requested feature and is live. Only the Panelica module does
    // today; cPanel/Plesk and the future Docker/Python/Node modules light up
    // their own tabs by declaring their own features, with no change here.
    // -------------------------------------------------------------------------

    /**
     * Resolve the server module for a hosting feature, or null when the feature
     * is not offered for this service (wrong module, no account, not live).
     */
    private function hostingModule(Service $service, string $feature): ?ServerModuleInterface
    {
        if (! $service->server_id || ! $this->isLive($service)) {
            return null;
        }
        $module = app(ProvisioningService::class)->resolveModule($service);
        if (! $module || ! method_exists($module, 'hostingFeatures')) {
            return null;
        }
        if (! in_array($feature, $module->hostingFeatures($service), true)) {
            return null;
        }

        return $module;
    }

    /** Email accounts tab. */
    public function emails(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $module = $this->hostingModule($service, 'emails');
        if (! $module) {
            return redirect()->route('client.services.show', $service);
        }

        $emails = $module->listEmails($service);
        $domains = $module->accountDomains($service);
        $webmailUrl = method_exists($module, 'webmailUrl') ? $module->webmailUrl($service) : null;

        return view('client.services.hosting.email', compact('service', 'emails', 'domains', 'webmailUrl'));
    }

    public function storeEmail(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $module = $this->hostingModule($service, 'emails');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }

        $data = $request->validate([
            'domain_id' => ['required', 'string'],
            'local_part' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9._%+\-]+$/'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
            'quota_mb' => ['nullable', 'integer', 'min:0', 'max:1048576'],
        ]);

        $result = $module->createEmail(
            $service,
            $data['domain_id'],
            $data['local_part'],
            $data['password'],
            (int) ($data['quota_mb'] ?? 0)
        );

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function destroyEmail(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $module = $this->hostingModule($service, 'emails');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }

        $result = $module->deleteEmail($service, (string) $request->input('email_id'));

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function updateEmailPassword(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $module = $this->hostingModule($service, 'emails');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }

        $data = $request->validate([
            'email_id' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        $result = $module->changeEmailPassword($service, $data['email_id'], $data['password']);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    // ----- Databases (Panelica-only) -----------------------------------------

    public function databases(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $module = $this->hostingModule($service, 'databases');
        if (! $module) {
            return redirect()->route('client.services.show', $service);
        }

        $groups = $module->listDatabases($service);
        $phpMyAdminUrl = method_exists($module, 'phpMyAdminUrl') ? $module->phpMyAdminUrl($service) : null;

        return view('client.services.hosting.databases', compact('service', 'groups', 'phpMyAdminUrl'));
    }

    public function storeDatabase(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'databases');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'domain_id' => ['required', 'string'],
            'database_name' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_]+$/'],
            'database_user' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_]+$/'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        $result = $module->createDatabase($service, $data['domain_id'], $data['database_name'], $data['database_user'], $data['password']);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function destroyDatabase(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'databases');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'domain_id' => ['required', 'string'],
            'database_name' => ['required', 'string'],
        ]);

        $result = $module->deleteDatabase($service, $data['domain_id'], $data['database_name']);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function storeDatabaseUser(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'databases');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'domain_id' => ['required', 'string'],
            'username' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_]+$/'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
            'role' => ['required', 'in:read,readWrite,dbAdmin,dbOwner'],
        ]);

        $result = $module->createDatabaseUser($service, $data['domain_id'], $data['username'], $data['password'], $data['role']);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function destroyDatabaseUser(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'databases');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $result = $module->deleteDatabaseUser($service, (string) $request->input('user_id'));

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function updateDatabaseUserPassword(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'databases');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'user_id' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        $result = $module->changeDatabaseUserPassword($service, $data['user_id'], $data['password']);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    // ----- Subdomains (Panelica-only) ----------------------------------------

    public function subdomains(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'subdomains');
        if (! $module) {
            return redirect()->route('client.services.show', $service);
        }
        $subdomains = $module->subdomains($service);
        $policy = $module->subdomainPolicy($service);
        $domains = $module->accountDomains($service);

        return view('client.services.hosting.subdomains', compact('service', 'subdomains', 'policy', 'domains'));
    }

    public function storeSubdomain(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'subdomains');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'domain_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:63', 'regex:/^[a-zA-Z0-9-]+$/'],
            'document_root' => ['nullable', 'string', 'max:255'],
            'php_version' => ['nullable', 'string', 'max:10'],
            'ssl' => ['nullable', 'boolean'],
        ]);

        $result = $module->createSubdomain(
            $service,
            $data['domain_id'],
            $data['name'],
            $data['document_root'] ?? null,
            $data['php_version'] ?? null,
            // A hidden ssl=0 sits before the checkbox so an unchecked box is
            // actually transmitted (a bare unchecked checkbox sends nothing,
            // which would silently force SSL on). boolean() reads the real state.
            $request->boolean('ssl')
        );

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function destroySubdomain(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'subdomains');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $result = $module->deleteSubdomain($service, (string) $request->input('subdomain_id'));

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function cron(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'cron');
        if (! $module) {
            return redirect()->route('client.services.show', $service);
        }
        $cronJobs = $module->cronJobs($service);
        $policy = $module->cronPolicy($service);
        $domains = $module->accountDomains($service);

        return view('client.services.hosting.cron', compact('service', 'cronJobs', 'policy', 'domains'));
    }

    public function storeCron(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'cron');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'domain_id' => ['required', 'string'],
            'task_name' => ['required', 'string', 'max:255'],
            'command' => ['required', 'string', 'max:4096'],
            'schedule_type' => ['nullable', 'in:basic,advanced'],
            'preset' => ['nullable', 'string', 'max:32'],
            'minute' => ['nullable', 'string', 'max:100'],
            'hour' => ['nullable', 'string', 'max:100'],
            'day_of_month' => ['nullable', 'string', 'max:100'],
            'month' => ['nullable', 'string', 'max:100'],
            'day_of_week' => ['nullable', 'string', 'max:100'],
            'email_on_error' => ['nullable', 'boolean'],
            'email_recipient' => ['nullable', 'email', 'max:255'],
        ]);

        $schedule = ($data['schedule_type'] ?? 'basic') === 'advanced'
            ? [
                'minute' => $data['minute'] ?? '*',
                'hour' => $data['hour'] ?? '*',
                'day_of_month' => $data['day_of_month'] ?? '*',
                'month' => $data['month'] ?? '*',
                'day_of_week' => $data['day_of_week'] ?? '*',
            ]
            : $this->presetToSchedule($data['preset'] ?? 'daily');

        $result = $module->createCronJob(
            $service,
            $data['domain_id'],
            $data['task_name'],
            $data['command'],
            $schedule,
            0,
            $request->boolean('email_on_error'),
            $data['email_recipient'] ?? ''
        );

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function toggleCron(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'cron');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $result = $module->toggleCronJob($service, (string) $request->input('cron_id'));

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function runCron(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'cron');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $result = $module->runCronJob($service, (string) $request->input('cron_id'));
        if ($result['success'] && ! empty($result['data']['output'])) {
            return back()->with('success', $result['message'])->with('cron_output', $result['data']['output']);
        }

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function destroyCron(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'cron');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $result = $module->deleteCronJob($service, (string) $request->input('cron_id'));

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function dns(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'dns');
        if (! $module) {
            return redirect()->route('client.services.show', $service);
        }
        $domains = $module->accountDomains($service);
        // A zone editor works on ONE zone at a time; ?domain= selects it and the
        // first domain is the default. Anything else would list every domain's
        // records in one flat table, which is unreadable and easy to misedit.
        $selected = (string) request('domain', '');
        if (! isset($domains[$selected])) {
            $selected = (string) array_key_first($domains);
        }
        $records = $selected !== '' ? $module->dnsRecords($service, $selected) : [];
        $types = $module->dnsRecordTypes();

        return view('client.services.hosting.dns', compact('service', 'records', 'domains', 'types', 'selected'));
    }

    public function storeDns(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'dns');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'domain_id' => ['required', 'string'],
            'type' => ['required', 'string', 'max:10'],
            'name' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:1024'],
            'ttl' => ['nullable', 'integer', 'min:60', 'max:604800'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $result = $module->createDnsRecord(
            $service,
            $data['domain_id'],
            $data['type'],
            $data['name'] ?? '@',
            $data['content'],
            $data['ttl'] ?? null,
            $data['priority'] ?? null
        );

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function updateDns(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'dns');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'record_id' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:1024'],
            'ttl' => ['nullable', 'integer', 'min:60', 'max:604800'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $result = $module->updateDnsRecord(
            $service,
            $data['record_id'],
            $data['name'] ?? '@',
            $data['content'],
            $data['ttl'] ?? null,
            $data['priority'] ?? null
        );

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function destroyDns(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'dns');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $result = $module->deleteDnsRecord($service, (string) $request->input('record_id'));

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function backups(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'backups');
        if (! $module) {
            return redirect()->route('client.services.show', $service);
        }
        $backups = $module->backups($service);
        $policy = $module->backupPolicy($service);
        $domains = $module->accountDomains($service);

        return view('client.services.hosting.backups', compact('service', 'backups', 'policy', 'domains'));
    }

    public function storeBackup(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'backups');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'domain_id' => ['nullable', 'string'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);
        $result = $module->createBackup($service, $data['domain_id'] ?? null, $data['name'] ?? '');

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function destroyBackup(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'backups');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $result = $module->deleteBackup($service, (string) $request->input('filename'));

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function containers(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'containers');
        if (! $module) {
            return redirect()->route('client.services.show', $service);
        }
        $containers = $module->containers($service);
        $policy = $module->containerPolicy($service);
        // The catalogue is only needed when the customer can actually install
        // something; skipping it otherwise saves a call on a locked plan.
        $templates = $policy['can_create'] ? $module->containerTemplates($service) : [];
        $resources = method_exists($module, 'containerResources') ? $module->containerResources($service) : ['memory_mb' => 0, 'cpu_percent' => 0];
        $groups = $this->groupTemplates($templates);
        // Our own images, one query for the page. The panel's logo_url is not
        // used: most apps have none and half the rest are dead links.
        $logos = \App\Models\DockerAppLogo::urlMap();

        return view('client.services.hosting.containers', compact('service', 'containers', 'policy', 'templates', 'resources', 'groups', 'logos'));
    }

    /**
     * The catalogue arranged into sections a customer can navigate.
     *
     * The panel tags apps with 51 different categories - "devtools", "nosql",
     * "whmcs-alternative" - which is a useful index for us and a wall of jargon
     * for the person choosing an app. These are the sections people actually
     * shop by; anything tagged with something new lands in Other rather than
     * disappearing, so the catalogue never hides an app we failed to map.
     *
     * @param  array<int, array<string, mixed>>  $templates
     * @return array<int, array{key:string, apps:array<int, array<string, mixed>>}>
     */
    private function groupTemplates(array $templates): array
    {
        $sections = [
            'websites' => ['web', 'cms', 'wordpress', 'blog', 'php', 'hosting', 'ecommerce'],
            'databases' => ['database', 'nosql', 'cache', 'search'],
            'ai' => ['ai', 'automation'],
            'devtools' => ['devtools', 'git', 'ide', 'runtime', 'base', 'os'],
            'monitoring' => ['monitoring', 'analytics', 'management'],
            'network' => ['networking', 'proxy', 'vpn', 'security', 'remote-access'],
            'desktops' => ['desktop', 'remote-desktop', 'browser'],
            'files' => ['storage', 'media', 'streaming'],
            'team' => ['collaboration', 'messaging', 'chat', 'email', 'wiki', 'notes',
                'documentation', 'knowledge-base', 'communication', 'voip', 'support', 'rss'],
        ];

        $out = [];
        $placed = [];
        foreach ($sections as $key => $cats) {
            $apps = [];
            foreach ($templates as $i => $t) {
                if (isset($placed[$i])) {
                    continue;
                }
                if (array_intersect($cats, (array) ($t['categories'] ?? []))) {
                    $apps[] = $t;
                    $placed[$i] = true;
                }
            }
            if ($apps) {
                $out[] = ['key' => $key, 'apps' => $apps];
            }
        }

        $rest = [];
        foreach ($templates as $i => $t) {
            if (! isset($placed[$i])) {
                $rest[] = $t;
            }
        }
        if ($rest) {
            $out[] = ['key' => 'other', 'apps' => $rest];
        }

        return $out;
    }

    public function storeContainer(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'containers');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:40'],
        ]);
        $result = $module->deployContainer($service, $data['slug'], $data['name'] ?? '');

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function containerAction(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'containers');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'container_id' => ['required', 'string'],
            'action' => ['required', 'in:start,stop,restart'],
        ]);
        $result = $module->containerAction($service, $data['container_id'], $data['action']);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function destroyContainer(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'containers');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $result = $module->deleteContainer($service, (string) $request->input('container_id'));

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /** Map a friendly preset to the 5-field cron schedule (mirrors the panel). */
    private function presetToSchedule(string $preset): array
    {
        return match ($preset) {
            'everyMinute' => ['minute' => '*', 'hour' => '*', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*'],
            'every5Minutes' => ['minute' => '*/5', 'hour' => '*', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*'],
            'every15Minutes' => ['minute' => '*/15', 'hour' => '*', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*'],
            'every30Minutes' => ['minute' => '*/30', 'hour' => '*', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*'],
            'hourly' => ['minute' => '0', 'hour' => '*', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*'],
            'weekly' => ['minute' => '0', 'hour' => '0', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '0'],
            'monthly' => ['minute' => '0', 'hour' => '0', 'day_of_month' => '1', 'month' => '*', 'day_of_week' => '*'],
            default => ['minute' => '0', 'hour' => '0', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*'], // daily
        };
    }

    // ----- FTP accounts (Panelica-only) --------------------------------------

    public function ftp(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'ftp');
        if (! $module) {
            return redirect()->route('client.services.show', $service);
        }
        $accounts = $module->ftpAccounts($service);
        $policy = $module->ftpPolicy($service);
        $domains = $module->accountDomains($service);
        $ftpHost = method_exists($module, 'ftpHost') ? $module->ftpHost($service) : null;

        return view('client.services.hosting.ftp', compact('service', 'accounts', 'policy', 'domains', 'ftpHost'));
    }

    public function storeFtp(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'ftp');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'username' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9._-]+$/i'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
            'domain_id' => ['nullable', 'string'],
            'quota_mb' => ['nullable', 'integer', 'min:0', 'max:1048576'],
        ]);

        $result = $module->createFtpAccount($service, $data['username'], $data['password'], $data['domain_id'] ?? null, (int) ($data['quota_mb'] ?? 0));

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function destroyFtp(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'ftp');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $result = $module->deleteFtpAccount($service, (string) $request->input('ftp_id'));

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function updateFtpPassword(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $module = $this->hostingModule($service, 'ftp');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'ftp_id' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
        ]);

        $result = $module->changeFtpPassword($service, $data['ftp_id'], $data['password']);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    // ----- File manager (Panelica-only) --------------------------------------

    /** File browser tab. */
    public function files(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $module = $this->hostingModule($service, 'files');
        if (! $module) {
            return redirect()->route('client.services.show', $service);
        }

        $listing = $module->listFiles($service, $request->query('path'));

        return view('client.services.hosting.files', compact('service', 'listing'));
    }

    /** Load a text file into the editor. */
    public function filesEdit(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $module = $this->hostingModule($service, 'files');
        $path = (string) $request->query('path');
        if (! $module || $path === '') {
            return redirect()->route('client.services.files', $service);
        }

        $result = $module->readFile($service, $path);
        if (! ($result['success'] ?? false)) {
            return redirect()
                ->route('client.services.files', ['service' => $service, 'path' => dirname($path)])
                ->with('error', $result['message']);
        }

        $content = $result['data']['content'] ?? '';

        return view('client.services.hosting.file-edit', compact('service', 'path', 'content'));
    }

    public function filesWrite(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $module = $this->hostingModule($service, 'files');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'path' => ['required', 'string'],
            'content' => ['nullable', 'string'],
        ]);

        $result = $module->writeFile($service, $data['path'], (string) ($data['content'] ?? ''));

        return redirect()
            ->route('client.services.files', ['service' => $service, 'path' => dirname($data['path'])])
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function filesCreate(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $module = $this->hostingModule($service, 'files');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'path' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:file,folder'],
        ]);

        $result = $module->createEntry($service, $data['path'], $data['name'], $data['type']);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function filesRename(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $module = $this->hostingModule($service, 'files');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'path' => ['required', 'string'],
            'new_name' => ['required', 'string', 'max:255'],
        ]);

        $result = $module->renameEntry($service, $data['path'], $data['new_name']);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function filesDelete(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $module = $this->hostingModule($service, 'files');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'paths' => ['required', 'array', 'min:1'],
            'paths.*' => ['string'],
        ]);

        $result = $module->deleteEntries($service, $data['paths']);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function filesUpload(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $module = $this->hostingModule($service, 'files');
        if (! $module) {
            return back()->with('error', __('client.hosting.unavailable'));
        }
        $data = $request->validate([
            'path' => ['required', 'string'],
            'file' => ['required', 'file', 'max:262144'], // 256 MB
        ]);

        $file = $data['file'];
        $result = $module->uploadFile(
            $service,
            $data['path'],
            file_get_contents($file->getRealPath()),
            $file->getClientOriginalName()
        );

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /** Stream a file download through the panel API (authenticated proxy). */
    public function filesDownload(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $module = $this->hostingModule($service, 'files');
        $path = (string) $request->query('path');
        if (! $module || $path === '') {
            abort(404);
        }

        $resp = $module->downloadFile($service, $path);
        if (! $resp) {
            return back()->with('error', __('client.hosting.files.download_failed'));
        }

        $name = basename($path) ?: 'download';

        return response($resp->body(), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.str_replace('"', '', $name).'"',
        ]);
    }
}
