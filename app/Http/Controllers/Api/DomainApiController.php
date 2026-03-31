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
        if ($request->filled("domain")) { $query->where("domain", "like", "%" . $request->domain . "%"); }
        $domains = $query->orderBy("id", "desc")->paginate($request->get("limitnum", 25));
        return $this->paginated($domains);
    }

    public function getDomainDetails(Request $request)
    {
        $domain = Domain::with("client")->find($request->domainid);
        if (!$domain) return $this->error("Domain Not Found", 404);
        return $this->success(["domain" => $domain->toArray()]);
    }

    public function updateDomain(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (!$domain) return $this->error("Domain Not Found", 404);
        $fields = ["status", "expiry_date", "next_due_date", "notes", "dns_management", "email_forwarding", "id_protection", "payment_method"];
        foreach ($fields as $f) {
            if ($request->has($f)) $domain->$f = $request->$f;
        }
        $domain->save();
        return $this->success(["domainid" => $domain->id]);
    }

    public function getNameservers(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (!$domain) return $this->error("Domain Not Found", 404);
        // Nameservers stored as JSON array or comma-separated string
        $ns = $domain->nameservers;
        if (is_string($ns)) {
            $decoded = json_decode($ns, true);
            $ns = is_array($decoded) ? $decoded : explode(",", $ns);
        }
        $ns = array_values(array_filter((array) $ns));
        return $this->success([
            "domainid" => $domain->id,
            "ns1" => $ns[0] ?? null,
            "ns2" => $ns[1] ?? null,
            "ns3" => $ns[2] ?? null,
            "ns4" => $ns[3] ?? null,
        ]);
    }

    public function updateNameservers(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (!$domain) return $this->error("Domain Not Found", 404);
        $ns = array_values(array_filter([$request->ns1, $request->ns2, $request->ns3, $request->ns4]));
        $domain->nameservers = json_encode($ns);
        $domain->save();
        return $this->success(["domainid" => $domain->id]);
    }

    public function getLockStatus(Request $request)
    {
        // Domain lock status — not all registrars expose this, default false
        $domain = Domain::find($request->domainid);
        if (!$domain) return $this->error("Domain Not Found", 404);
        return $this->success(["domainid" => $domain->id, "lockstatus" => false]);
    }

    public function getTldPricing(Request $request)
    {
        $pricing = DomainPricing::where("enabled", true)->orderBy("sort_order")->get();
        return $this->success(["pricing" => $pricing->toArray()]);
    }
}
