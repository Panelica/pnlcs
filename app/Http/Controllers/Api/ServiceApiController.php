<?php

namespace App\Http\Controllers\Api;

use App\Models\CancellationRequest;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\ProvisioningService;
use Illuminate\Http\Request;

class ServiceApiController extends BaseApiController
{
    public function __construct(private ProvisioningService $provisioning) {}

    public function getClientsProducts(Request $request)
    {
        $query = Service::with('client', 'product');
        if ($request->filled('userid')) {
            $query->where('client_id', $request->userid);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->paginated($query->orderBy('id', 'desc')->paginate($request->get('limitnum', 25)));
    }

    public function updateClientProduct(Request $request)
    {
        $service = Service::find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }
        foreach (['status', 'domain', 'username', 'password', 'next_due_date', 'billing_cycle', 'notes'] as $f) {
            if ($request->has($f)) {
                $service->$f = $request->$f;
            }
        }
        $service->save();

        return $this->success(['serviceid' => $service->id]);
    }

    public function moduleCreate(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }
        $result = $this->provisioning->createAccount($service);

        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }

    public function moduleSuspend(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }
        $result = $this->provisioning->suspendAccount($service, $request->get('reason', ''));

        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }

    public function moduleUnsuspend(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }
        $result = $this->provisioning->unsuspendAccount($service);

        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }

    public function moduleTerminate(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }
        $result = $this->provisioning->terminateAccount($service);

        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }

    public function moduleChangePassword(Request $request)
    {
        $request->validate(['serviceid' => 'required', 'servicepassword' => 'nullable|string|min:6', 'password' => 'nullable|string|min:6']);
        $service = Service::with('product')->find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }
        $pw = $request->servicepassword ?? $request->password ?? '';
        if (empty($pw)) {
            return $this->error('Password required');
        }
        $result = $this->provisioning->changePassword($service, $pw);

        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }

    public function moduleChangePw(Request $request)
    {
        return $this->moduleChangePassword($request);
    }

    public function moduleChangePackage(Request $request)
    {
        $request->validate(['serviceid' => 'required', 'packageid' => 'required|integer']);
        $service = Service::with('product')->find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }
        $product = Product::find($request->packageid);
        if (! $product) {
            return $this->error('Product Not Found', 404);
        }
        $result = $this->provisioning->changePackage($service, $product);

        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }

    public function getClientsAddons(Request $request)
    {
        $query = ServiceAddon::with('service', 'addon');
        if ($request->filled('serviceid')) {
            $query->where('service_id', $request->serviceid);
        }
        if ($request->filled('userid')) {
            $query->whereHas('service', fn ($q) => $q->where('client_id', $request->userid));
        }

        return $this->success(['addons' => $query->get()->toArray()]);
    }

    public function updateClientAddon(Request $request)
    {
        $addon = ServiceAddon::find($request->id);
        if (! $addon) {
            return $this->error('Addon Not Found', 404);
        }
        if ($request->has('status')) {
            $addon->status = $request->status;
        }
        if ($request->has('notes')) {
            $addon->notes = $request->notes;
        }
        $addon->save();

        return $this->success(['addonid' => $addon->id]);
    }

    public function moduleCustom(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }

        // No server module exposes custom functions, and saying one ran is
        // worse than saying there is nothing to run.
        return $this->error('Custom module functions are not implemented.', 501);
    }

    public function upgradeProduct(Request $request)
    {
        $validated = $request->validate([
            'serviceid' => 'required|exists:services,id',
            'packageid' => 'required|exists:products,id',
        ]);

        $service = Service::with('product', 'client')->findOrFail($validated['serviceid']);
        $newProduct = \App\Models\Product::with('pricing')->findOrFail($validated['packageid']);

        // Writing product_id on its own left the customer on a bigger plan at
        // the old price, with the difference unbilled and the server untold.
        $result = app(\App\Services\UpgradeService::class)->requestProductChange($service, $newProduct);

        if (! $result['success']) {
            return $this->error($result['message'] ?? 'The package change was refused.', 422);
        }

        return $this->success([
            'serviceid' => $service->id,
            'upgradeid' => $result['upgrade']->id,
            'invoiceid' => $result['invoice']->id ?? null,
            'applied' => $result['applied'],
        ]);
    }

    public function addCancelRequest(Request $request)
    {
        $service = Service::find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }
        CancellationRequest::create(['service_id' => $service->id, 'type' => $request->get('type', 'end_of_billing'), 'reason' => $request->get('reason', '')]);

        return $this->success(['serviceid' => $service->id]);
    }

    public function getCancelledPackages(Request $request)
    {
        $items = CancellationRequest::with('service')->paginate($this->getPerPage(), ['*'], 'page', $this->getPage());

        return $this->paginated($items);
    }

    public function addProduct(Request $request)
    {
        // A refusal, not a success: nothing was created.
        return $this->error('Services are created through addorder.', 501);
    }
}
