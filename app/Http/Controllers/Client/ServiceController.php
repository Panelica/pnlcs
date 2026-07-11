<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\CancellationRequest;
use App\Models\Product;
use App\Models\Service;
use App\Models\Upgrade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\CancellationConfirmMail;

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

        $service->load("client");
        if ($service->client?->email) {
            Mail::to($service->client->email)->queue(new CancellationConfirmMail($service, $validated["type"]));
        }

        return redirect()->route("client.services.show", $service)
            ->with("success", __("messages.success.cancellation_request_submitted"));
    }

    public function upgrade(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $service->load("product");

        $availableProducts = Product::active()
            ->where("id", "!=", $service->product_id)
            ->with("pricing")
            ->get();

        return view("client.services.upgrade", ["service" => $service, "upgrades" => $availableProducts]);
    }

    public function processUpgrade(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $validated = $request->validate([
            "new_product_id" => "required|exists:products,id",
        ]);

        $newProduct = Product::with("pricing")->findOrFail($validated["new_product_id"]);

        if ((int) $newProduct->id === (int) $service->product_id) {
            return back()->with("error", __("messages.error.already_on_this_product"));
        }

        $upgrades = app(\App\Services\UpgradeService::class);
        $calc = $upgrades->calculateProration($service, $newProduct);

        if (!($calc["available"] ?? false)) {
            return back()->with("error", __("messages.error.upgrade_not_available_for_cycle"));
        }

        $service->loadMissing("product", "client");
        $currentName = $service->product->name ?? "Current plan";

        $upgrade = Upgrade::create([
            "client_id"      => $this->getClientId(),
            "type"           => "product",
            "rel_id"         => $service->id,
            "original_value" => $service->product_id,
            "new_value"      => $newProduct->id,
            "amount"         => $calc["prorated_diff"],
            "status"         => "pending",
        ]);

        // Positive prorated cost -> bill it; ApplyUpgradeListener applies the
        // package change once the invoice is paid.
        if ($calc["prorated_diff"] > 0.009) {
            $invoice = app(\App\Services\InvoiceService::class)->createInvoice($service->client, [[
                "type"        => "Upgrade",
                "rel_id"      => $upgrade->id,
                "description" => "Upgrade: {$currentName} -> {$newProduct->name} ({$service->billing_cycle}) - prorated for {$calc['remaining_days']} days - {$service->domain}",
                "amount"      => $calc["prorated_diff"],
                "taxed"       => $newProduct->tax ?? true,
            ]], ["notes" => "Prorated product upgrade charge."]);

            return redirect()->route("client.invoices.show", $invoice)
                ->with("success", __("messages.success.upgrade_invoice_created"));
        }

        // Downgrade or same price -> apply immediately, no charge (no auto-credit).
        $upgrades->apply($upgrade);

        return redirect()->route("client.services.show", $service->fresh())
            ->with("success", __("messages.success.package_changed"));
    }

    public function toggleAutoRenew(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $service->update([
            "auto_renew" => !$service->auto_renew,
        ]);

        $status = $service->auto_renew ? "enabled" : "disabled";
        return back()->with("success", __("messages.success.auto_renewal_toggled", ["status" => $status, "domain" => $service->domain]));
    }

    private function getClientId(): int
    {
        return auth()->user()->clients()->first()?->id ?? 0;
    }
}
