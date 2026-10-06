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
use App\Services\ProvisioningService;
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

        return view('admin.services.show', compact('service', 'availableAddons', 'vpsFeatures', 'reinstallChoices', 'products', 'cycles', 'productFields'));
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

        $result = match ($action) {
            'create' => $this->provisioning->createAccount($service),
            'suspend' => $this->provisioning->suspendAccount($service, $request->get('reason', '')),
            'unsuspend' => $this->provisioning->unsuspendAccount($service),
            'terminate' => $this->provisioning->terminateAccount($service),
            'changepassword' => $this->provisioning->changePassword($service, $request->get('password', '')),
            'pve_start', 'pve_shutdown', 'pve_reboot', 'pve_stop', 'pve_reset' => $this->proxmox($service)?->power($service, substr($action, 4))
                ?? ['success' => false, 'message' => __('admin.messages.unknown_action', ['action' => $action])],
            'pve_claim' => $this->proxmox($service)?->claim($service, (int) $request->validate(['vmid' => 'required|integer|min:100'])['vmid'])
                ?? ['success' => false, 'message' => __('admin.messages.unknown_action', ['action' => $action])],
            default => ['success' => false, 'message' => __('admin.messages.unknown_action', ['action' => $action])],
        };

        if ($result['success'] ?? false) {
            return back()->with('success', str_starts_with($action, 'pve_')
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

    public function updateStatus(Request $request, Service $service)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_column(ServiceStatus::cases(), 'value'))],
        ]);

        $status = $validated['status'];

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

        return back()->with('success', __('admin.messages.service_status_updated', ['status' => $status]));
    }

    public function destroy(Request $request, Service $service)
    {
        // Deleting the row does not delete the account: a live one on a
        // server would carry on running with nothing left to bill it or say
        // who it belongs to. Terminate first, then delete.
        $live = in_array(strtolower((string) $service->status), [ServiceStatus::Active->value, ServiceStatus::Suspended->value], true)
            && $this->provisioning->resolveModule($service) !== null;

        if ($live) {
            return back()->with('error', __('admin.messages.service_live_cannot_delete'));
        }

        $service->delete();

        return redirect()->route('admin.services.index')->with('success', __('admin.messages.service_deleted'));
    }
}
