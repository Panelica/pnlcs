<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CancellationRequest;
use App\Models\Product;
use App\Models\Service;
use App\Models\Upgrade;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::with("product")
            ->where("client_id", $this->getClientId())
            ->orderBy("id", "desc")
            ->paginate(25);

        return view("client.services.index", compact("services"));
    }

    public function show(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $service->load("product", "server", "addons");

        return view("client.services.show", compact("service"));
    }

    public function requestCancellation(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        return view("client.services.cancel", compact("service"));
    }

    public function submitCancellation(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $validated = $request->validate([
            "type"   => "required|in:Immediate,End of Billing Period",
            "reason" => "required|string|max:1000",
        ]);

        CancellationRequest::create([
            "service_id" => $service->id,
            "type"       => $validated["type"],
            "reason"     => $validated["reason"],
        ]);

        return redirect()->route("client.services.show", $service)
            ->with("success", "Your cancellation request has been submitted.");
    }

    public function upgrade(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $service->load("product");

        $availableProducts = Product::active()
            ->where("id", "!=", $service->product_id)
            ->with("pricing")
            ->get();

        return view("client.services.upgrade", compact("service", "availableProducts"));
    }

    public function processUpgrade(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $validated = $request->validate([
            "new_product_id" => "required|exists:products,id",
        ]);

        $newProduct = Product::findOrFail($validated["new_product_id"]);

        Upgrade::create([
            "client_id"      => $this->getClientId(),
            "type"           => "product",
            "rel_id"         => $service->id,
            "original_value" => $service->product_id,
            "new_value"      => $newProduct->id,
            "amount"         => 0,
            "status"         => "pending",
        ]);

        return redirect()->route("client.services.show", $service)
            ->with("success", "Your upgrade request has been submitted and is pending review.");
    }

    private function getClientId(): int
    {
        return auth()->user()->clients()->first()?->id ?? 0;
    }
}
