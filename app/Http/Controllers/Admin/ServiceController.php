<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\ServiceStatus;
use App\Models\ProductAddon;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\AddonService;
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

        return view('admin.services.show', compact('service', 'availableAddons'));
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
            default => ['success' => false, 'message' => __('admin.messages.unknown_action', ['action' => $action])],
        };

        if ($result['success'] ?? false) {
            return back()->with('success', __('admin.messages.module_action_success', ['action' => ucfirst($action)]));
        }

        return back()->with('error', $result['message'] ?? __('admin.messages.module_action_failed'));
    }

    /**
     * Manually change the billing/status state of a service. This only flips the
     * status flag (and the relevant date) on the record; it does not talk to the
     * server module. Use the module actions for that.
     */
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
        $service->delete();

        return redirect()->route('admin.services.index')->with('success', __('admin.messages.service_deleted'));
    }
}
