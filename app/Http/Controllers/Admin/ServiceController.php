<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\ProvisioningService;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(private ProvisioningService $provisioning) {}

    public function index(Request $request)
    {
        $query = Service::with('client', 'product');
        if ($request->filled('status')) { $query->where('status', $request->status); }
        $services = $query->orderBy('created_at', 'desc')->paginate(25);
        return view('admin.services.index', compact('services'));
    }

    public function show(Service $service)
    {
        $service->load('client', 'product', 'server', 'addons', 'order');
        return view('admin.services.show', compact('service'));
    }

    public function moduleAction(Request $request, Service $service, string $action)
    {
        $service->load('product');

        $result = match ($action) {
            'create'         => $this->provisioning->createAccount($service),
            'suspend'        => $this->provisioning->suspendAccount($service, $request->get('reason', '')),
            'unsuspend'      => $this->provisioning->unsuspendAccount($service),
            'terminate'      => $this->provisioning->terminateAccount($service),
            'changepassword' => $this->provisioning->changePassword($service, $request->get('password', '')),
            default          => ['success' => false, 'message' => __('admin.messages.unknown_action', ['action' => $action])],
        };

        if ($result['success'] ?? false) {
            return back()->with('success', __('admin.messages.module_action_success', ['action' => ucfirst($action)]));
        }

        return back()->with('error', $result['message'] ?? __('admin.messages.module_action_failed'));
    }
}
