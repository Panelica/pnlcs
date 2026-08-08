<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Mail\CancellationConfirmMail;
use App\Models\CancellationRequest;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\AddonService;
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

        // Reading is not a reason to choose a server. Asking a module for one
        // makes it pick and write it onto the service, so a customer opening
        // this page used to nail an order that was never provisioned to
        // whichever server the module happened to return.
        if (! $service->server_id) {
            return response()->json(['available' => false]);
        }

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

        // Cancelled, terminated, marked fraud: the customer may no longer act
        // on this service. The cancellation form has always asked; this did
        // not, and the button being hidden is no answer - the route answers
        // whoever calls it, and a customer who kept the URL still has it.
        if (! $this->isLive($service)) {
            return back()->with('error', __('client.services.not_live_for_action'));
        }

        // No server yet means there is no account to sign in to; asking the
        // module would pick a server and bind the service to it.
        if (! $service->server_id) {
            return back()->with('error', __('messages.error.panel_login_unavailable'));
        }

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
        // Only one open request at a time. It used to be one ever: a
        // request that had already been acted on blocked the customer from
        // asking again for the rest of the service's life.
        if (CancellationRequest::where('service_id', $service->id)->whereNull('processed_at')->exists()) {
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

        // r127-cycle: the customer's own term decides both what is on offer and
        // what it costs. The screen used to list every active package and work
        // the price out in the view by taking the first cycle with a figure in
        // it - monthly, almost always - so somebody on an annual service was
        // shown monthly prices, and packages not sold annually at all were
        // offered to them and refused after they had chosen one.
        $cycle = $service->billing_cycle ?: 'Monthly';

        $prices = [];

        $availableProducts = Product::active()
            ->where('id', '!=', $service->product_id)
            ->with('pricing')
            ->get()
            ->filter(function (Product $product) use ($cycle, &$prices) {
                $price = $product->priceFor($cycle);

                if ($price === null) {
                    return false;
                }

                $prices[$product->id] = $price;

                return true;
            })
            ->values();

        return view('client.services.upgrade', [
            'service' => $service,
            'upgrades' => $availableProducts,
            'upgradePrices' => $prices,
            'upgradeCycle' => $cycle,
        ]);
    }

    public function processUpgrade(Request $request, Service $service)
    {
        abort_if($service->client_id !== $this->getClientId(), 403);

        $validated = $request->validate([
            'new_product_id' => 'required|exists:products,id',
        ]);

        $newProduct = Product::with('pricing')->findOrFail($validated['new_product_id']);

        $result = app(UpgradeService::class)->requestProductChange($service, $newProduct);

        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        if ($result['invoice']) {
            return redirect()->route('client.invoices.show', $result['invoice'])
                ->with('success', __('messages.success.upgrade_invoice_created'));
        }

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
