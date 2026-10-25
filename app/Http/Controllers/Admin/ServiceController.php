<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\ServiceStatus;
use App\Models\ActivityLog;
use App\Models\Domain;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\AddonService;
use App\Services\BillingCycleChange;
use App\Services\Module\ModuleScreens;
use App\Services\ProvisioningService;
use App\Services\ServerAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function __construct(private ProvisioningService $provisioning) {}

    public function index(Request $request)
    {
        $query = Service::with('client', 'product');
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $services = $query->orderBy('created_at', 'desc')->paginate(25);

        return view('admin.services.index', compact('services'));
    }

    public function show(Service $service)
    {
        $service->load('client', 'product', 'server', 'addons.addon', 'order');

        $availableAddons = $service->product
            ? app(AddonService::class)->availableFor($service->product)
            : collect();

        $vpsFeatures = [];
        $reinstallChoices = [];
        if ($module = $this->proxmox($service)) {
            $vpsFeatures = $module->vpsFeatures($service);
            $reinstallChoices = \Modules\Servers\Proxmox\ProxmoxPlan::forService($service)->reinstallChoices();
        }

        // For the edit form.
        $products = Product::orderBy('name')->get(['id', 'name', 'server_type']);
        $cycles = self::cyclesFor($service);

        // The answers to the product's own questions, asked when it was ordered.
        $productFields = $service->product_id ? \App\Models\CustomField::productFields((int) $service->product_id)->get() : collect();

        // What the service's module adds to this page (ModuleScreens).
        $screens = ModuleScreens::for($service);
        $moduleScreen = [
            'panel' => $screens->adminPanel(),
            'buttons' => $screens->adminButtons(),
            'provisioned' => $screens->isProvisioned(),
            'change_password' => $screens->shows('change_password'),
        ];

        // A setup that has not gone through: why, and when it is tried again.
        // It used to show nowhere; the reason was in the retry queue (#212).
        $pendingSetup = \App\Models\ModuleQueue::where('service_id', $service->id)
            ->where('action', 'create')
            ->whereIn('status', ['pending', 'failed'])
            ->latest('id')
            ->first();

        return view('admin.services.show', compact('service', 'availableAddons', 'vpsFeatures', 'reinstallChoices', 'products', 'cycles', 'productFields', 'moduleScreen', 'pendingSetup'));
    }

    /**
     * Put an addon on a service. It is invoiced like any other purchase, so the
     * money still gets collected rather than the addon being given away.
     */
    public function storeAddon(Request $request, Service $service)
    {
        $request->validate(['addon_id' => 'required|integer|exists:product_addons,id']);

        app(AddonService::class)->purchaseForService(
            $service,
            ProductAddon::findOrFail($request->integer('addon_id'))
        );

        return back()->with('success', __('admin.messages.addon_created'));
    }

    public function cancelAddon(Service $service, ServiceAddon $addon)
    {
        abort_if($addon->service_id !== $service->id, 404);

        app(AddonService::class)->cancel($addon);

        return back()->with('success', __('admin.messages.addon_updated'));
    }

    public function updateNextDue(Request $request, Service $service)
    {
        $validated = $request->validate([
            'next_due_date' => ['required', 'date'],
        ]);

        $service->update(['next_due_date' => $validated['next_due_date']]);

        return back()->with('success', __('admin.messages.service_next_due_updated'));
    }

    public function moduleAction(Request $request, Service $service, string $action)
    {
        $service->load('product');

        // The box on the page asks for six characters, but that is the browser
        // asking. Anything else reaching this route sent whatever it liked
        // straight to the control panel, and an empty field arrives as null,
        // which fell over on the way there. The API door for the same operation
        // has always checked this.
        if ($action === 'changepassword') {
            $request->validate(['password' => ['required', 'string', 'min:6']]);
        }

        // The reason is shown to the customer (service page, suspension email)
        // and to the next member of staff; it is kept short.
        if ($action === 'suspend') {
            $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        }

        $result = match ($action) {
            'create' => $this->provisioning->createAccount($service),
            'suspend' => $this->provisioning->suspendAccount($service, trim((string) $request->input('reason', ''))),
            'unsuspend' => $this->provisioning->unsuspendAccount($service),
            'terminate' => $this->provisioning->terminateAccount($service),
            'changepassword' => $this->provisioning->changePassword($service, $request->get('password', '')),
            'pve_start', 'pve_shutdown', 'pve_reboot', 'pve_stop', 'pve_reset' => $this->proxmox($service)?->power($service, substr($action, 4))
                ?? ['success' => false, 'message' => __('admin.messages.unknown_action', ['action' => $action])],
            'pve_claim' => $this->proxmox($service)?->claim($service, (int) $request->validate(['vmid' => 'required|integer|min:100'])['vmid'])
                ?? ['success' => false, 'message' => __('admin.messages.unknown_action', ['action' => $action])],
            // A button the module itself offers (customFunctions()); its own
            // message says what happened.
            default => ModuleScreens::for($service)->run('admin', $action)
                ?? ['success' => false, 'message' => __('admin.messages.unknown_action', ['action' => $action])],
        };

        if ($result['success'] ?? false) {
            $own = str_starts_with($action, 'pve_') || ! in_array($action, ['create', 'suspend', 'unsuspend', 'terminate', 'changepassword'], true);

            return back()->with('success', $own && filled($result['message'] ?? null)
                ? $result['message']
                : __('admin.messages.module_action_success', ['action' => ucfirst($action)]));
        }

        return back()->with('error', $result['message'] ?? __('admin.messages.module_action_failed'));
    }

    private function proxmox(Service $service): ?\Modules\Servers\Proxmox\ProxmoxModule
    {
        $module = $this->provisioning->resolveModule($service);

        return $module instanceof \Modules\Servers\Proxmox\ProxmoxModule ? $module : null;
    }

    /**
     * Manually change the billing/status state of a service. This only flips the
     * status flag (and the relevant date) on the record; it does not talk to the
     * server module. Use the module actions for that.
     */
    /** The cycles orders write, plus whatever the service already carries so saving never rewrites it. */
    private static function cyclesFor(Service $service): array
    {
        $cycles = array_values(BillingCycleChange::CYCLES);
        if ($service->billing_cycle && ! in_array($service->billing_cycle, $cycles, true)) {
            array_unshift($cycles, (string) $service->billing_cycle);
        }

        return $cycles;
    }

    /**
     * Edit a service's record: product, domain, username, billing cycle,
     * recurring amount, a "do not suspend until" date and notes.
     *
     * The page could only change the due date and the status; everything
     * else needed the API or the database.
     */
    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            // The name the server module creates the account for.
            'domain' => ['nullable', 'string', 'max:253', function ($attribute, $value, $fail) {
                if (! filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || ! str_contains((string) $value, '.')) {
                    $fail(__('admin.services.domain_invalid'));
                }
            }],
            'username' => 'nullable|string|max:255',
            'billing_cycle' => ['required', Rule::in(self::cyclesFor($service))],
            'amount' => 'required|numeric|min:0|max:99999999',
            'override_auto_suspend_date' => 'nullable|date',
            'notes' => 'nullable|string|max:20000',
        ]);

        $newProduct = (int) $validated['product_id'] !== (int) $service->product_id ? Product::find($validated['product_id']) : null;

        // A service that has an account on a server moves through the module,
        // so the account is resized too; if the server refuses, nothing is
        // changed. Without an account (pending, terminated, or no module) the
        // record is all there is.
        if ($newProduct) {
            $currentType = strtolower((string) ($service->server?->type ?? $service->product?->server_type ?? ''));
            $newType = strtolower((string) ($newProduct->server_type ?? ''));

            // Moving to a different server module — or to a module-less product
            // such as SSL — means the account must be recreated (or unbound), so
            // drop the old binding and its account data and let provisioning pick
            // a fresh server.
            if ($newType !== $currentType) {
                // An account that still runs on its server would be left there
                // with nothing pointing at it: never suspended, never terminated,
                // never billed. It is closed through the module first.
                if ($service->server_id && in_array($service->status, [ServiceStatus::Active->value, ServiceStatus::Suspended->value], true)) {
                    return back()->withInput()->with('error', __('admin.services.module_change_needs_termination'));
                }

                $service->server_id = null;
                $service->module_data = null;
                $service->username = null;
                $service->password = null;
                $service->status = ServiceStatus::Pending->value;
                $service->product_id = $newProduct->id;
            } elseif ($currentType !== '' && in_array($service->status, [ServiceStatus::Active->value, ServiceStatus::Suspended->value], true)) {
                $result = $this->provisioning->changePackage($service, $newProduct);
                if (! ($result['success'] ?? false)) {
                    return back()->withInput()->with('error', __('admin.services.package_change_failed', ['message' => $result['message'] ?? '']));
                }
            } else {
                $service->product_id = $newProduct->id;
            }
        }

        $service->fill([
            'domain' => $validated['domain'] !== null ? (Domain::normalise($validated['domain']) ?: null) : null,
            'username' => $validated['username'],
            'billing_cycle' => $validated['billing_cycle'],
            'amount' => $validated['amount'],
            'override_auto_suspend_date' => $validated['override_auto_suspend_date'],
            'notes' => $validated['notes'],
        ])->save();

        ActivityLog::log("Service #{$service->id} edited", auth('admin')->user()->username, $service->client_id);

        return back()->with('success', __('admin.services.service_saved'));
    }

    /**
     * The status buttons change the record only. Closing it (Terminated,
     * Cancelled) while its account is still on the server first asks what
     * happens to the account: terminated there too (the default), or kept
     * there on purpose. Otherwise the account kept running unbilled and its
     * domain could not be ordered again (#212).
     */
    public function updateStatus(Request $request, Service $service)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_column(ServiceStatus::cases(), 'value'))],
            'server' => ['nullable', Rule::in(['terminate', 'keep'])],
        ]);

        $status = $validated['status'];
        $done = __('admin.messages.service_status_updated', ['status' => $status]);
        $closing = in_array($status, [ServiceStatus::Terminated->value, ServiceStatus::Cancelled->value], true)
            && $status !== strtolower((string) $service->status);

        if ($closing && ServerAccount::needsDecision($state = ServerAccount::state($service))) {
            $decision = ['action' => 'status', 'status' => $status, 'state' => $state];
            $choice = $validated['server'] ?? null;

            if ($choice === null) {
                return back()->with('server_decision', $decision);
            }

            if ($choice === 'terminate') {
                $result = $this->provisioning->terminateAccount($service, queueOnFail: false);
                if (! ($result['success'] ?? false)) {
                    return back()->with('error', __('admin.services.server_terminate_failed_status', ['message' => (string) ($result['message'] ?? '')]))
                        ->with('server_decision', $decision);
                }
                // Terminating marked it Terminated; Cancelled is the record's word for it.
                if ($status === ServiceStatus::Cancelled->value) {
                    $service->update(['status' => $status]);
                }

                return back()->with('success', __('admin.services.status_closed_on_server', ['status' => status_label($status)]));
            }

            ActivityLog::log("Service #{$service->id} marked {$status}; its account on the server was kept", auth('admin')->user()?->username, $service->client_id);
            $done = __('admin.services.status_kept_on_server', ['status' => status_label($status)]);
        }

        $data = ['status' => $status];

        if ($status === ServiceStatus::Suspended->value) {
            $data['suspension_date'] = now();
        } elseif ($status === ServiceStatus::Terminated->value) {
            $data['termination_date'] = now();
            $data['suspension_date'] = null;
        } elseif (in_array($status, [ServiceStatus::Active->value, ServiceStatus::Pending->value], true)) {
            $data['suspension_date'] = null;
            $data['termination_date'] = null;
        }

        $service->update($data);

        return back()->with('success', $done);
    }

    /**
     * Deleting the row does not delete the account. When the account is (or
     * may still be) on its server, staff choose first: terminate it there and
     * delete (the default), or delete the record only and keep the account.
     * A termination the server refuses deletes nothing (#212).
     */
    public function destroy(Request $request, Service $service)
    {
        $validated = $request->validate(['server' => ['nullable', Rule::in(['terminate', 'keep'])]]);
        $done = __('admin.messages.service_deleted');

        if (ServerAccount::needsDecision($state = ServerAccount::state($service))) {
            $decision = ['action' => 'delete', 'state' => $state];
            $choice = $validated['server'] ?? null;

            if ($choice === null) {
                return back()->with('server_decision', $decision);
            }

            if ($choice === 'terminate') {
                $result = $this->provisioning->terminateAccount($service, queueOnFail: false);
                if (! ($result['success'] ?? false)) {
                    return back()->with('error', __('admin.services.server_terminate_failed_delete', ['message' => (string) ($result['message'] ?? '')]))
                        ->with('server_decision', $decision);
                }
                $done = __('admin.services.terminated_and_deleted');
            } else {
                $service->loadMissing('server');
                ActivityLog::log("Service #{$service->id} ({$service->domain}) deleted; its account on the server was kept", auth('admin')->user()?->username, $service->client_id);
                $done = __('admin.services.deleted_kept_on_server', ['server' => (string) ($service->server?->name ?? '')]);
            }
        }

        $service->delete();

        return redirect()->route('admin.services.index')->with('success', $done);
    }
}
