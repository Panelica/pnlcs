<?php
namespace App\Http\Controllers\Client;
use App\Http\Controllers\Controller;
use App\Models\Domain;

class DomainController extends Controller
{
    public function index() {
        $domains = Domain::where("client_id", $this->getClientId())->orderBy("id","desc")->paginate(25);
        return view("client.domains.index", compact("domains"));
    }
    private function getClientId() { return auth()->user()->clients()->first()?->id ?? 0; }
}
