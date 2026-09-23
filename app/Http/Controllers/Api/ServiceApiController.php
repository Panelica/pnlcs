<?php

namespace App\Http\Controllers\Api;

use App\Enums\ServiceStatus;
use Illuminate\Validation\Rule;
use App\Models\CancellationRequest;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\ProvisioningService;
use App\Services\UpgradeService;
use Illuminate\Http\Request;

class ServiceApiController extends BaseApiController
{
    public function __construct(private ProvisioningService $provisioning) {}

    public function getClientsProducts(Request $request)
    {
        $query = Service::with('client', 'product');
        // WHMCS names the customer filter clientid and also filters by
        // serviceid, pid and domain. Only userid was read here, so asking for
        // one customer's services - or for one service - returned every
        // service in the installation.
        $clientId = $request->input('clientid', $request->input('userid'));
        if (filled($clientId)) {
            $query->where('client_id', $clientId);
        }
        if ($request->filled('serviceid')) {
            $query->whereKey($request->serviceid);
        }
        if ($request->filled('pid')) {
            $query->where('product_id', $request->pid);
        }
        if ($request->filled('domain')) {
            $query->where('domain', $request->domain);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->paginated($query->orderBy('id', 'desc')->paginate($this->getPerPage(), ['*'], 'page', $this->getPage()));
    }

    public function updateClientProduct(Request $request)
    {
        $service = Service::find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }

        // The three fields the rest of the panel reads back as facts. status
        // decides whether the service is still billed, suspended or cancelled
        // by the nightly runs, and it is not cast to the enum, so a typo was
        // written as-is and the service quietly matched none of those queries
        // again. The billing cycle is restricted at the checkout to the six the
        // pricing tables carry, and the due date is a date column that was
        // taking any string at all.
        $request->validate([
            'status' => ['sometimes', Rule::enum(ServiceStatus::class)],
            'billing_cycle' => ['sometimes', 'in:onetime,monthly,quarterly,semiannually,annually,biennially,triennially'],
            'next_due_date' => ['sometimes', 'date'],
            // The name the server module creates the account for; a value that
            // is not a hostname could never be provisioned or found again.
            'domain' => ['sometimes', 'nullable', 'string', 'max:253', function ($attribute, $value, $fail) {
                if ($value !== null && $value !== '' && (! filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || ! str_contains((string) $value, '.'))) {
                    $fail('The domain must be a domain name.');
                }
            }],
        ]);

        foreach (['status', 'domain', 'username', 'password', 'next_due_date', 'billing_cycle', 'notes'] as $f) {
            if ($request->has($f)) {
                $service->$f = $request->$f;
            }
        }
        $service->save();

        return $this->success(['serviceid' => $service->id]);
    }

    public function moduleCreate(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }
        $result = $this->provisioning->createAccount($service);

        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }

    public function moduleSuspend(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }
        $result = $this->provisioning->suspendAccount($service, $request->get('reason', ''));

        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }

    public function moduleUnsuspend(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }
        $result = $this->provisioning->unsuspendAccount($service);

        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }

    public function moduleTerminate(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }
        $result = $this->provisioning->terminateAccount($service);

        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }

    public function moduleChangePassword(Request $request)
    {
        $request->validate(['serviceid' => 'required', 'servicepassword' => 'nullable|string|min:6', 'password' => 'nullable|string|min:6']);
        $service = Service::with('product')->find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }
        $pw = $request->servicepassword ?? $request->password ?? '';
        if (empty($pw)) {
            return $this->error('Password required');
        }
        $result = $this->provisioning->changePassword($service, $pw);

        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }

    public function moduleChangePw(Request $request)
    {
        return $this->moduleChangePassword($request);
    }

    public function moduleChangePackage(Request $request)
    {
        $request->validate(['serviceid' => 'required', 'packageid' => 'required|integer']);
        $service = Service::with('product')->find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }
        $product = Product::find($request->packageid);
        if (! $product) {
            return $this->error('Product Not Found', 404);
        }
        $result = $this->provisioning->changePackage($service, $product);

        return ($result['success'] ?? false) ? $this->success($result) : $this->error($result['message'] ?? 'Module action failed');
    }

    public function getClientsAddons(Request $request)
    {
        // WHMCS names the customer filter clientid here.
        $this->alias($request, 'clientid', 'userid');
        $query = ServiceAddon::with('service', 'addon');
        if ($request->filled('serviceid')) {
            $query->where('service_id', $request->serviceid);
        }
        if ($request->filled('userid')) {
            $query->whereHas('service', fn ($q) => $q->where('client_id', $request->userid));
        }

        return $this->success(['addons' => $query->get()->toArray()]);
    }

    public function updateClientAddon(Request $request)
    {
        $addon = ServiceAddon::find($request->id);
        if (! $addon) {
            return $this->error('Addon Not Found', 404);
        }
        // The addon billing run matches "active" exactly; any other word left
        // the addon neither billed nor cancelled. Cancelling goes through the
        // service that also stops the next due date.
        $request->validate(['status' => ['sometimes', Rule::enum(ServiceStatus::class)]]);
        if ($request->has('status')) {
            $status = strtolower((string) $request->status);
            if ($status === ServiceStatus::Cancelled->value) {
                app(\App\Services\AddonService::class)->cancel($addon);
                $addon->refresh();
            } else {
                $addon->status = $status;
            }
        }
        if ($request->has('notes')) {
            $addon->notes = $request->notes;
        }
        $addon->save();

        return $this->success(['addonid' => $addon->id]);
    }

    public function moduleCustom(Request $request)
    {
        $service = Service::with('product')->find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }

        // No server module exposes custom functions, and saying one ran is
        // worse than saying there is nothing to run.
        return $this->error('Custom module functions are not implemented.', 501);
    }

    public function upgradeProduct(Request $request)
    {
        $validated = $request->validate([
            'serviceid' => 'required|exists:services,id',
            'packageid' => 'required|exists:products,id',
        ]);

        $service = Service::with('product', 'client')->findOrFail($validated['serviceid']);
        $newProduct = Product::with('pricing')->findOrFail($validated['packageid']);

        // Writing product_id on its own left the customer on a bigger plan at
        // the old price, with the difference unbilled and the server untold.
        $result = app(UpgradeService::class)->requestProductChange($service, $newProduct);

        if (! $result['success']) {
            return $this->error($result['message'] ?? 'The package change was refused.', 422);
        }

        return $this->success([
            'serviceid' => $service->id,
            'upgradeid' => $result['upgrade']->id,
            'invoiceid' => $result['invoice']->id ?? null,
            'applied' => $result['applied'],
        ]);
    }

    public function addCancelRequest(Request $request)
    {
        $service = Service::find($request->serviceid);
        if (! $service) {
            return $this->error('Service Not Found', 404);
        }

        // The two types the customer area writes. This wrote "end_of_billing"
        // by default and took any word at all, so the requests it made read
        // differently from every other one.
        $request->validate([
            'type' => ['nullable', 'string', Rule::in(['Immediate', 'End of Billing Period', 'immediate', 'end_of_billing'])],
            'reason' => 'nullable|string|max:1000',
        ]);
        $type = match (strtolower((string) $request->input('type', 'End of Billing Period'))) {
            'immediate' => 'Immediate',
            default => 'End of Billing Period',
        };

        // Same rule as the customer area: a service that is already gone has
        // nothing left to cancel.
        if (in_array(strtolower((string) $service->status), ['terminated', 'cancelled', 'fraud'], true)) {
            return $this->error('The service is not live, so there is nothing to cancel.', 422);
        }

        $open = CancellationRequest::where('service_id', $service->id)->whereNull('processed_at')->first();

        if ($open) {
            return $this->error('A cancellation request is already open for this service', 409);
        }

        CancellationRequest::create(['service_id' => $service->id, 'type' => $type, 'reason' => (string) $request->input('reason', '')]);

        return $this->success(['serviceid' => $service->id]);
    }

    public function getCancelledPackages(Request $request)
    {
        $items = CancellationRequest::with('service')->orderBy('id', 'desc')->paginate($this->getPerPage(), ['*'], 'page', $this->getPage());

        return $this->paginated($items);
    }

    public function addProduct(Request $request)
    {
        // A refusal, not a success: nothing was created.
        return $this->error('Services are created through addorder.', 501);
    }
}
