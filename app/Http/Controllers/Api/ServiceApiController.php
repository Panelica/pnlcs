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
        $query = Service::with('client','product');
        if ($request->filled('userid')) $query->where('client_id', $request->userid);
        if ($request->filled('status')) $query->where('status', $request->status);
        return $this->paginated($query->orderBy('id','desc')->paginate($request->get('limitnum',25)));
    }

    public function updateClientProduct(Request $request)
    {
        $service = Service::find($request->serviceid);
        if (!$service) return $this->error('Service Not Found', 404);
        foreach(['status','domain','username','password','next_due_date','billing_cycle','notes'] as $f) { if($request->has($f)) $service->$f=$request->$f; }
        $service->save();
        return $this->success(['serviceid'=>$service->id]);
    }

    public function moduleCreate(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (!$service) return $this->error('Service Not Found', 404);
        $result = $this->provisioning->createAccount($service);
        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }

    public function moduleSuspend(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (!$service) return $this->error('Service Not Found', 404);
        $result = $this->provisioning->suspendAccount($service, $request->get('reason',''));
        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }

    public function moduleUnsuspend(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (!$service) return $this->error('Service Not Found', 404);
        $result = $this->provisioning->unsuspendAccount($service);
        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }

    public function moduleTerminate(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (!$service) return $this->error('Service Not Found', 404);
        $result = $this->provisioning->terminateAccount($service);
        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }

    public function moduleChangePassword(Request $request)
    {
        $request->validate(['serviceid'=>'required','servicepassword'=>'nullable|string|min:6','password'=>'nullable|string|min:6']);
        $service = Service::with('product')->find($request->serviceid);
        if (!$service) return $this->error('Service Not Found', 404);
        $pw = $request->servicepassword ?? $request->password ?? '';
        if (empty($pw)) return $this->error('Password required');
        $result = $this->provisioning->changePassword($service, $pw);
        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }
    public function moduleChangePw(Request $request) { return $this->moduleChangePassword($request); }

    public function moduleChangePackage(Request $request)
    {
        $request->validate(['serviceid'=>'required','packageid'=>'required|integer']);
        $service = Service::with('product')->find($request->serviceid);
        if (!$service) return $this->error('Service Not Found', 404);
        $product = Product::find($request->packageid);
        if (!$product) return $this->error('Product Not Found', 404);
        $result = $this->provisioning->changePackage($service, $product);
        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }

    public function getClientsAddons(Request $request)
    {
        $query = \App\Models\ServiceAddon::with('service','product');
        if ($request->filled('serviceid')) $query->where('service_id', $request->serviceid);
        if ($request->filled('userid')) $query->whereHas('service', fn($q) => $q->where('client_id', $request->userid));
        return $this->success(['addons' => $query->get()->toArray()]);
    }

    public function updateClientAddon(Request $request)
    {
        $addon = \App\Models\ServiceAddon::find($request->id);
        if (!$addon) return $this->error('Addon Not Found', 404);
        if ($request->has('status')) $addon->status = $request->status;
        if ($request->has('notes')) $addon->notes = $request->notes;
        $addon->save();
        return $this->success(['addonid' => $addon->id]);
    }

    public function moduleCustom(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (!$service) return $this->error('Service Not Found', 404);
        return $this->success(['serviceid' => $service->id, 'result' => 'Custom function executed']);
    }

    public function upgradeProduct(Request $request)
    {
        $service = Service::find($request->serviceid);
        if (!$service) return $this->error('Service Not Found', 404);
        if ($request->filled('packageid')) { $service->product_id = $request->packageid; $service->save(); }
        return $this->success(['serviceid' => $service->id]);
    }

    public function addCancelRequest(Request $request)
    {
        $service = Service::find($request->serviceid);
        if (!$service) return $this->error('Service Not Found', 404);
        \App\Models\CancellationRequest::create(['service_id'=>$service->id,'type'=>$request->get('type','end_of_billing'),'reason'=>$request->get('reason','')]);
        return $this->success(['serviceid' => $service->id]);
    }

    public function getCancelledPackages(Request $request)
    {
        $items = \App\Models\CancellationRequest::with('service')->paginate($this->getPerPage(), ["*"], "page", $this->getPage());
        return $this->paginated($items);
    }

    public function addProduct(Request $request) { return $this->success(['message' => 'Use addorder to create services']); }
}
