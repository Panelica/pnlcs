<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductAddon;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\AddonService;
use App\Services\ProvisioningService;
use Illuminate\Http\Request;

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

    public function moduleAction(Request $request, Service $service, string $action)
    {
        $service->load('product');

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
}
