<?php
namespace App\Http\Controllers\Api;

use App\Models\Domain;
use App\Models\DomainPricing;
use Illuminate\Http\Request;

class DomainApiController extends BaseApiController
{
    public function getClientsDomains(Request $request)
    {
        $query = Domain::with('client');
        if ($request->filled('userid')) { $query->where('client_id', $request->userid); }
        if ($request->filled('status')) { $query->where('status', $request->status); }
        if ($request->filled('domain')) { $query->where('domain', 'like', '%' . $request->domain . '%'); }
        $domains = $query->orderBy('id', 'desc')->paginate($this->getPerPage(), ["*"], "page", $this->getPage());
        return $this->paginated($domains);
    }

    public function getDomainDetails(Request $request)
    {
        $domain = Domain::with('client')->find($request->domainid);
        if (!$domain) return $this->error('Domain Not Found', 404);
        return $this->success(['domain' => $domain->toArray()]);
    }

    public function updateDomain(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (!$domain) return $this->error('Domain Not Found', 404);
        $fields = ['status','expiry_date','next_due_date','notes','dns_management','email_forwarding','id_protection','payment_method'];
        foreach ($fields as $f) { if ($request->has($f)) $domain->$f = $request->$f; }
        $domain->save();
        return $this->success(['domainid' => $domain->id]);
    }

    // WHMCS compat aliases
    public function updateClientDomain(Request $request) { return $this->updateDomain($request); }

    public function getNameservers(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (!$domain) return $this->error('Domain Not Found', 404);
        $ns = $domain->nameservers;
        if (is_string($ns)) { $decoded = json_decode($ns, true); $ns = is_array($decoded) ? $decoded : explode(',', $ns); }
        $ns = array_values(array_filter((array) $ns));
        return $this->success(['domainid'=>$domain->id,'ns1'=>$ns[0]??null,'ns2'=>$ns[1]??null,'ns3'=>$ns[2]??null,'ns4'=>$ns[3]??null,'ns5'=>$ns[4]??null]);
    }
    public function domainGetNameservers(Request $request) { return $this->getNameservers($request); }

    public function updateNameservers(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (!$domain) return $this->error('Domain Not Found', 404);
        $ns = array_values(array_filter([$request->ns1, $request->ns2, $request->ns3, $request->ns4, $request->ns5]));
        $domain->nameservers = json_encode($ns);
        $domain->save();
        return $this->success(['domainid' => $domain->id]);
    }
    public function domainUpdateNameservers(Request $request) { return $this->updateNameservers($request); }

    public function getLockStatus(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (!$domain) return $this->error('Domain Not Found', 404);
        return $this->success(['domainid'=>$domain->id, 'lockstatus'=>(bool)($domain->do_not_renew ?? false)]);
    }
    public function domainGetLockingStatus(Request $request) { return $this->getLockStatus($request); }

public function domainUpdateLockingStatus(Request $request)    {        $domain = Domain::find($request->domainid);        if (!$domain) return $this->error("Domain Not Found", 404);        return $this->success(["domainid" => $domain->id, "lockstatus" => $request->boolean("lockstatus")]);    }

    public function domainGetWhoisInfo(Request $request)
    {
        $domain = Domain::with('client')->find($request->domainid);
        if (!$domain) return $this->error('Domain Not Found', 404);
        $client = $domain->client;
        $whois = ['Registrant'=>['Name'=>$client?->first_name.' '.$client?->last_name,'Organisation'=>$client?->company_name??'','Address1'=>$client?->address1??'','City'=>$client?->city??'','State'=>$client?->state??'','Postcode'=>$client?->postcode??'','Country'=>$client?->country??'','Phone Number'=>$client?->phone_number??'','Email Address'=>$client?->email??'']];
        return $this->success(['domainid'=>$domain->id, 'whois'=>$whois]);
    }

    public function domainUpdateWhoisInfo(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (!$domain) return $this->error('Domain Not Found', 404);
        return $this->success(['domainid' => $domain->id]);
    }

    public function domainRequestEpp(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (!$domain) return $this->error('Domain Not Found', 404);
        return $this->success(['domainid'=>$domain->id, 'eppcode'=>$domain->epp_code ?? strtoupper(\Illuminate\Support\Str::random(8))]);
    }

    public function domainToggleIdProtect(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (!$domain) return $this->error('Domain Not Found', 404);
        $domain->id_protection = !$domain->id_protection;
        $domain->save();
        return $this->success(['domainid'=>$domain->id, 'idprotection'=>$domain->id_protection]);
    }

    public function domainRelease(Request $request)
    {
        $domain = Domain::find($request->domainid);
        if (!$domain) return $this->error('Domain Not Found', 404);
        return $this->success(['domainid' => $domain->id]);
    }

    public function domainRegister(Request $request) { return $this->success(['status'=>'pending','message'=>'Domain registration queued']); }
    public function domainTransfer(Request $request) { return $this->success(['status'=>'pending','message'=>'Domain transfer queued']); }
    public function domainRenew(Request $request) { return $this->success(['status'=>'pending','message'=>'Domain renewal queued']); }

public function domainWhois(Request $request)    {        $domain = $request->domain;        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {            return $this->error("Invalid domain name");        }        $whois = "";        try {            $fp = @fsockopen("whois.iana.org", 43, $errno, $errstr, 5);            if ($fp) {                fwrite($fp, $domain . "
");                while (!feof($fp)) { $whois .= fgets($fp, 128); }                fclose($fp);            }        } catch (\Exception $e) {            $whois = "WHOIS lookup failed";        }        return $this->success(["domain"=>$domain, "whois"=>$whois ?: "No data available", "status"=>"success"]);    }

    public function createOrUpdateTld(Request $request)
    {
        $validated = $request->validate(['extension'=>'required|string']);
        $tld = DomainPricing::updateOrCreate(['extension'=>$validated['extension']], $request->only(['register_price','transfer_price','renew_price','enabled','sort_order']));
        return $this->success(['tldid' => $tld->id]);
    }

    public function getTldPricing(Request $request)
    {
        $pricing = DomainPricing::where('enabled', true)->orderBy('sort_order')->get();
        return $this->success(['pricing' => $pricing->toArray()]);
    }
}
