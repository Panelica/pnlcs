<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesClient;
use App\Mail\CancellationConfirmMail;
use App\Models\CancellationRequest;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\Upgrade;
use App\Services\AddonService;
use App\Services\InvoiceService;
use App\Services\ProvisioningService;
use App\Services\UpgradeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ServiceController extends Controller
{
    use ResolvesClient;

    public function index()
    {
        $services = Service::with('product')
            ->where('client_id', $this->getClientId())
            ->orderBy('id', 'desc')
            ->paginate(25);

        return view('client.services.index', compact('services'));
    }

    public function show(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $service->load('product', 'server', 'addons.addon');

        $availableAddons = $service->product
            ? app(AddonService::class)->availableFor($service->product)
                ->reject(fn ($addon) => $service->addons
                    ->where('addon_id', $addon->id)
                    ->whereIn('status', ['active', 'pending'])
                    ->isNotEmpty())
                ->values()
            : collect();

        return view('client.services.show', compact('service', 'availableAddons'));
    }

    /** Order an addon for a running service; it starts once its invoice is paid. */
    public function storeAddon(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $request->validate(['addon_id' => 'required|integer|exists:product_addons,id']);

        $result = app(AddonService::class)->purchaseForService(
            $service,
            ProductAddon::findOrFail($request->integer('addon_id'))
        );

        return redirect()->route('client.invoices.show', $result['invoice'])
            ->with('success', __('client.services.addon_ordered'));
    }

    /** Stop an addon without disturbing the service. */
    public function cancelAddon(Service $service, ServiceAddon $addon)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        abort_if($addon->service_id !== $service->id, 404);

        app(AddonService::class)->cancel($addon);

        return back()->with('success', __('client.services.addon_cancelled'));
    }

    /**
     * Live resource usage (disk / bandwidth / counts) for the client usage graphs.
     */
    public function usage(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $module = app(ProvisioningService::class)->resolveModule($service);
        if (! $module || ! method_exists($module, 'liveUsage')) {
            return response()->json(['available' => false]);
        }

        return response()->json($module->liveUsage($service));
    }

    /**
     * Single sign-on into the hosting control panel for this service.
     */
    public function loginToPanel(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $module = app(ProvisioningService::class)->resolveModule($service);
        if (! $module || ! method_exists($module, 'ssoLogin')) {
            return back()->with('error', __('messages.error.panel_login_unavailable'));
        }

        $result = $module->ssoLogin($service);
        if (($result['success'] ?? false) && ! empty($result['data']['url'])) {
            return redirect()->away($result['data']['url']);
        }

        return back()->with('error', $result['message'] ?? __('messages.error.panel_login_unavailable'));
    }

    public function requestCancellation(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        return view('client.services.cancel', compact('service'));
    }

    public function submitCancellation(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $validated = $request->validate([
            'type' => 'required|in:Immediate,End of Billing Period',
            'reason' => 'required|string|max:1000',
        ]);

        if (! $this->isLive($service)) {
            return back()->with('error', __('client.services.not_live_for_action'));
        }

        // A second request would send another confirmation and give the
        // cancellation cron two rows for the same service.
        if (CancellationRequest::where('service_id', $service->id)->exists()) {
            return back()->with('error', __('client.services.cancellation_already_requested'));
        }

        CancellationRequest::create([
            'service_id' => $service->id,
            'type' => $validated['type'],
            'reason' => $validated['reason'],
        ]);

        $service->load('client');
        if ($service->client?->email) {
            Mail::to($service->client->email)->queue(new CancellationConfirmMail($service, $validated['type']));
        }

        return redirect()->route('client.services.show', $service)
            ->with('success', __('messages.success.cancellation_request_submitted'));
    }

    public function upgrade(Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);
        $service->load('product');

        $availableProducts = Product::active()
            ->where('id', '!=', $service->product_id)
            ->with('pricing')
            ->get();

        return view('client.services.upgrade', ['service' => $service, 'upgrades' => $availableProducts]);
    }

    public function processUpgrade(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $validated = $request->validate([
            'new_product_id' => 'required|exists:products,id',
        ]);

        if (! $this->isLive($service)) {
            return back()->with('error', __('client.services.not_live_for_action'));
        }

        $newProduct = Product::with('pricing')->findOrFail($validated['new_product_id']);

        // The upgrade page only lists what the shop is selling; the request
        // that follows it accepted any product id at all.
        if ($newProduct->hidden || $newProduct->retired) {
            return back()->with('error', __('client.cart.product_unavailable'));
        }

        if ((int) $newProduct->id === (int) $service->product_id) {
            return back()->with('error', __('messages.error.already_on_this_product'));
        }

        $upgrades = app(UpgradeService::class);
        $calc = $upgrades->calculateProration($service, $newProduct);

        if (! ($calc['available'] ?? false)) {
            return back()->with('error', __('messages.error.upgrade_not_available_for_cycle'));
        }

        $service->loadMissing('product', 'client');
        $currentName = $service->product->name ?? 'Current plan';

        $upgrade = Upgrade::create([
            'client_id' => $this->getClientId(),
            'type' => 'product',
            'rel_id' => $service->id,
            'original_value' => $service->product_id,
            'new_value' => $newProduct->id,
            'amount' => $calc['prorated_diff'],
            'status' => 'pending',
        ]);

        // Positive prorated cost -> bill it; ApplyUpgradeListener applies the
        // package change once the invoice is paid.
        if ($calc['prorated_diff'] > 0.009) {
            $invoice = app(InvoiceService::class)->createInvoice($service->client, [[
                'type' => 'Upgrade',
                'rel_id' => $upgrade->id,
                'description' => "Upgrade: {$currentName} -> {$newProduct->name} ({$service->billing_cycle}) - prorated for {$calc['remaining_days']} days - {$service->domain}",
                'amount' => $calc['prorated_diff'],
                'taxed' => $newProduct->tax ?? true,
            ]], ['notes' => 'Prorated product upgrade charge.']);

            return redirect()->route('client.invoices.show', $invoice)
                ->with('success', __('messages.success.upgrade_invoice_created'));
        }

        // Downgrade or same price -> apply immediately, no charge (no auto-credit).
        $upgrades->apply($upgrade);

        return redirect()->route('client.services.show', $service->fresh())
            ->with('success', __('messages.success.package_changed'));
    }

    public function toggleAutoRenew(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $service->update([
            'auto_renew' => ! $service->auto_renew,
        ]);

        $status = $service->auto_renew ? 'enabled' : 'disabled';

        return back()->with('success', __('messages.success.auto_renewal_toggled', ['status' => $status, 'domain' => $service->domain]));
    }

    /** A service that has ended cannot be upgraded, cancelled or renewed. */
    private function isLive(Service $service): bool
    {
        return ! in_array(strtolower((string) $service->status), ['terminated', 'cancelled', 'fraud'], true);
    }

}
