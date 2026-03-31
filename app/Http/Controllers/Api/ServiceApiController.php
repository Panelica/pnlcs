<?php
namespace App\Http\Controllers\Api;

use App\Models\Service;
use Illuminate\Http\Request;

class ServiceApiController extends BaseApiController
{
    public function getClientsProducts(Request $request)
    {
        $query = Service::with("client", "product");
        if ($request->filled("userid")) { $query->where("client_id", $request->userid); }
        if ($request->filled("status")) { $query->where("status", $request->status); }
        $services = $query->orderBy("id", "desc")->paginate($request->get("limitnum", 25));
        return $this->paginated($services);
    }

    public function updateClientProduct(Request $request)
    {
        $service = Service::find($request->serviceid);
        if (!$service) return $this->error("Service Not Found", 404);
        $fields = ["status", "domain", "username", "password", "next_due_date", "billing_cycle"];
        foreach ($fields as $f) {
            if ($request->has($f)) $service->$f = $request->$f;
        }
        $service->save();
        return $this->success(["serviceid" => $service->id]);
    }
}
