<?php
namespace App\Http\Controllers\Api;

use App\Models\Domain;
use App\Models\DomainPricing;
use Illuminate\Http\Request;

class DomainApiController extends BaseApiController
{
    public function getClientsDomains(Request $request)
    {
        $query = Domain::with("client");
        if ($request->filled("userid")) { $query->where("client_id", $request->userid); }
        if ($request->filled("status")) { $query->where("status", $request->status); }
        $domains = $query->orderBy("id", "desc")->paginate($request->get("limitnum", 25));
        return $this->paginated($domains);
    }

    public function getTldPricing(Request $request)
    {
        $pricing = DomainPricing::where("enabled", true)->orderBy("sort_order")->get();
        return $this->success(["pricing" => $pricing->toArray()]);
    }
}
