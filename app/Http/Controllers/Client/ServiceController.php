<?php
namespace App\Http\Controllers\Client;
use App\Http\Controllers\Controller;
use App\Models\Service;

class ServiceController extends Controller
{
    public function index() {
        $services = Service::with("product")->where("client_id", $this->getClientId())->orderBy("id","desc")->paginate(25);
        return view("client.services.index", compact("services"));
    }
    public function show(Service $service) {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $service->load("product", "server", "addons");
        return view("client.services.show", compact("service"));
    }
    private function getClientId() {
        return auth()->user()->clients()->first()?->id ?? 0;
    }
}
