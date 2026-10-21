<?php

namespace Modules\Servers\PanelicaLicense\Http\Admin;

use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Services\ProductCreator;
use App\Support\UniqueSlug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Modules\Servers\PanelicaLicense\CentralClient;
use Modules\Servers\PanelicaLicense\PanelicaLicenseModule;
use Modules\Servers\PanelicaLicense\PartnerAccount;

/**
 * The Panelica Licenses page (admin top menu): the partner account, the
 * plans with the partner's cost beside the selling price, one-click products,
 * and what has been sold.
 */
class LicensesPageController
{
    /** The product group the products made here go into. */
    private const GROUP_SLUG = 'panelica-licenses';

    public function index()
    {
        $module = app(PanelicaLicenseModule::class);
        $account = null;
        $accountError = null;
        $plans = [];

        if ($client = CentralClient::partner()) {
            $me = $client->get('me');
            if ($me->ok) {
                $account = (array) $me->data;
                $plans = $module->plans();
            } else {
                $accountError = $me->error;
            }
        }

        $currency = Currency::getDefault();
        $usdRate = $this->usdRate($currency);

        $products = Product::with('pricing')->where('server_type', PanelicaLicenseModule::NAME)->orderBy('id')->get();
        $productsByPlan = $products->groupBy(fn (Product $product) => (string) (($product->config_options ?? [])['package_name'] ?? ''));

        $services = Service::with('client', 'product')
            ->whereHas('product', fn ($q) => $q->where('server_type', PanelicaLicenseModule::NAME))
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $count = ['waiting' => 0, 'licensed' => 0, 'attention' => 0];
        foreach ($services as $service) {
            $data = $service->module_data ?? [];
            if (! empty($data['last_error'])) {
                $count['attention']++;
            }
            if (! empty($data['license_id']) && empty($data['ended'])) {
                $count['licensed']++;
            } elseif (! empty($data['install_ref']) && empty($data['ended']) && $service->status === 'active') {
                $count['waiting']++;
            }
        }

        return view('admin.panelica-licenses.index', [
            'connected' => PartnerAccount::connected(),
            'centralUrl' => PartnerAccount::centralUrl(),
            'customCentral' => PartnerAccount::centralUrl() !== PartnerAccount::DEFAULT_CENTRAL,
            'account' => $account,
            'accountError' => $accountError,
            'plans' => $plans,
            'productsByPlan' => $productsByPlan,
            'services' => $services,
            'count' => $count,
            'currency' => $currency,
            'usdRate' => $usdRate,
            'portal' => PartnerAccount::portalUrl(),
            'topUp' => PartnerAccount::portalUrl('customer/reseller/balance'),
            'becomePartner' => PartnerAccount::centralUrl().'/partnership',
            'keyExample' => PartnerAccount::KEY_EXAMPLE,
            'defaultCentral' => PartnerAccount::DEFAULT_CENTRAL,
        ]);
    }

    /** Try the key at Central first; only an active partner account is kept. */
    public function connect(Request $request)
    {
        $validated = $request->validate([
            'api_key' => ['required', 'string', 'starts_with:rk_', 'max:200'],
            'central_url' => ['nullable', 'url:http,https', 'max:255'],
        ]);

        $central = trim((string) ($validated['central_url'] ?? '')) ?: PartnerAccount::DEFAULT_CENTRAL;
        $me = CentralClient::make($central, $validated['api_key'])->get('me');

        if (! $me->ok) {
            return back()->withInput($request->except('api_key'))
                ->with('error', __('panelica_license.page.connect_failed', ['error' => $me->error]));
        }

        $status = (string) ($me->data['status'] ?? '');
        if (! in_array($status, ['active', 'approved'], true)) {
            return back()->withInput($request->except('api_key'))
                ->with('error', __('panelica_license.page.account_not_active', ['status' => $status]));
        }

        PartnerAccount::save($validated['api_key'], $central === PartnerAccount::DEFAULT_CENTRAL ? null : $central);

        return redirect()->route('admin.panelica-licenses.index')
            ->with('success', __('panelica_license.page.connected', ['name' => (string) ($me->data['company_name'] ?? '')]));
    }

    public function disconnect()
    {
        PartnerAccount::forget();

        return redirect()->route('admin.panelica-licenses.index')->with('success', __('panelica_license.page.disconnected'));
    }

    /**
     * A product that sells one Panelica plan: the Panelica Licenses group,
     * this module, the plan, monthly and/or annual prices in the default
     * currency (other currencies by their rate), set up when the first
     * payment arrives.
     */
    public function createProduct(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'monthly' => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
            'annually' => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
        ]);

        if (blank($validated['monthly'] ?? null) && blank($validated['annually'] ?? null)) {
            return back()->withInput()->with('error', __('panelica_license.page.price_needed'));
        }

        $plan = collect(app(PanelicaLicenseModule::class)->plans())->firstWhere('id', $validated['plan_id']);
        if (! $plan) {
            return back()->with('error', __('panelica_license.page.plan_unknown'));
        }

        $group = ProductGroup::where('slug', self::GROUP_SLUG)->first() ?? ProductGroup::create([
            'name' => __('panelica_license.page.group_name'),
            'slug' => UniqueSlug::for(ProductGroup::class, self::GROUP_SLUG, 'group'),
            'headline' => __('panelica_license.page.group_headline'),
        ]);

        $pricing = [];
        foreach (Currency::all() as $currency) {
            $rate = $currency->is_default ? 1.0 : (float) $currency->rate;
            $pricing[$currency->id] = [
                'monthly' => filled($validated['monthly'] ?? null) && $rate > 0 ? round((float) $validated['monthly'] * $rate, 2) : -1,
                'annually' => filled($validated['annually'] ?? null) && $rate > 0 ? round((float) $validated['annually'] * $rate, 2) : -1,
            ];
        }

        $product = app(ProductCreator::class)->create([
            'name' => $validated['name'],
            'group_id' => $group->id,
            'type' => 'other',
            'description' => __('panelica_license.page.product_description', ['plan' => (string) ($plan['name'] ?? '')]),
            'pay_type' => 'recurring',
            'auto_setup' => 'payment',
            'server_type' => PanelicaLicenseModule::NAME,
        ], $pricing, (string) $plan['id']);

        return back()->with('success', __('panelica_license.page.product_created', ['name' => $product->name]));
    }

    /** Look for installed servers and read licenses back now, not in five minutes. */
    public function sync()
    {
        Artisan::call('pnlcs:panelica-licenses');

        return back()->with('success', __('panelica_license.page.synced'));
    }

    /**
     * How many units of the default currency one US dollar is (Central prices
     * in dollars), or null when the site has no dollar rate to go by.
     */
    private function usdRate(?Currency $default): ?float
    {
        if (! $default) {
            return null;
        }
        if (strtoupper((string) $default->code) === 'USD') {
            return 1.0;
        }

        $usd = Currency::whereRaw('UPPER(code) = ?', ['USD'])->first();

        return $usd && (float) $usd->rate > 0 ? 1 / (float) $usd->rate : null;
    }
}
