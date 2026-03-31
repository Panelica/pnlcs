<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\Service;
use App\Services\ProvisioningService;
use Illuminate\Http\Request;

class ServiceApiController extends BaseApiController
{
    public function __construct(private ProvisioningService $provisioning) {}

    public function getClientsProducts(Request $request)
    {
        $query = Service::with('client', 'product');
        if ($request->filled('userid')) { $query->where('client_id', $request->userid); }
        if ($request->filled('status')) { $query->where('status', $request->status); }
        $services = $query->orderBy('id', 'desc')->paginate($request->get('limitnum', 25));
        return $this->paginated($services);
    }

    public function updateClientProduct(Request $request)
    {
        $service = Service::find($request->serviceid);
        if (!$service) return $this->error('Service Not Found', 404);
        $fields = ['status', 'domain', 'username', 'password', 'next_due_date', 'billing_cycle'];
        foreach ($fields as $f) {
            if ($request->has($f)) $service->$f = $request->$f;
        }
        $service->save();
        return $this->success(['serviceid' => $service->id]);
    }

    public function moduleCreate(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (!$service) return $this->error('Service Not Found', 404);

        $result = $this->provisioning->createAccount($service);

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? 'Module action failed');
        }

        return $this->success($result);
    }

    public function moduleSuspend(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (!$service) return $this->error('Service Not Found', 404);

        $result = $this->provisioning->suspendAccount($service, $request->get('reason', ''));

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? 'Module action failed');
        }

        return $this->success($result);
    }

    public function moduleUnsuspend(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (!$service) return $this->error('Service Not Found', 404);

        $result = $this->provisioning->unsuspendAccount($service);

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? 'Module action failed');
        }

        return $this->success($result);
    }

    public function moduleTerminate(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (!$service) return $this->error('Service Not Found', 404);

        $result = $this->provisioning->terminateAccount($service);

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? 'Module action failed');
        }

        return $this->success($result);
    }

    public function moduleChangePassword(Request $request)
    {
        $request->validate(['serviceid' => 'required', 'password' => 'required|string|min:6']);

        $service = Service::with('product')->find($request->serviceid);
        if (!$service) return $this->error('Service Not Found', 404);

        $result = $this->provisioning->changePassword($service, $request->password);

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? 'Module action failed');
        }

        return $this->success($result);
    }

    public function moduleChangePackage(Request $request)
    {
        $request->validate(['serviceid' => 'required', 'packageid' => 'required|integer']);

        $service = Service::with('product')->find($request->serviceid);
        if (!$service) return $this->error('Service Not Found', 404);

        $product = Product::find($request->packageid);
        if (!$product) return $this->error('Product Not Found', 404);

        $result = $this->provisioning->changePackage($service, $product);

        if (!($result['success'] ?? false)) {
            return $this->error($result['message'] ?? 'Module action failed');
        }

        return $this->success($result);
    }
}
