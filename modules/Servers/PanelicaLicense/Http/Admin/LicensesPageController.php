<?php

namespace Modules\Servers\PanelicaLicense\Http\Admin;

use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductTerm;
use App\Models\Setting;
use App\Models\Service;
use App\Services\BillingCycleHelper;
use App\Services\ExchangeRateService;
use App\Services\ProductCreator;
use App\Support\UniqueSlug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Servers\PanelicaLicense\CentralClient;
use Modules\Servers\PanelicaLicense\CentralResponse;
use Modules\Servers\PanelicaLicense\LicenseSync;
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
    /**
     * The terms this page sells, in months: every term Central sells.
     *
     * One, three, six and twelve months are stored on the matching pricing
     * column, because they have a cycle named after them; the rest are stored
     * as product terms. Either way the shop sells them, dates them and renews
     * them by their length (App\Services\BillingCycleHelper).
     */
    public const SELLABLE_MONTHS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

    /** The four of those terms a billing cycle is named after. */
    public const SELLABLE_CYCLES = ['monthly', 'quarterly', 'semiannually', 'annually'];

    private const GROUP_SLUG = 'panelica-licenses';

    public function index()
    {
        $this->guard();
        $module = app(PanelicaLicenseModule::class);
        $account = null;
        $accountError = null;
        $plans = [];
        $link = null;
        $centralLicenses = ['rows' => [], 'total' => 0];
        $installs = ['rows' => [], 'total' => 0];
        $balance = null;
        $movements = [];

        if ($client = CentralClient::partner()) {
            $me = $client->get('me');
            if ($me->ok) {
                $account = (array) $me->data;
                $plans = $module->plans();
                // What the partner account holds at Panelica, whatever sold it:
                // its licenses, the servers installed with its link, the link.
                $answer = $client->get('install-link');
                $link = $answer->ok ? (array) $answer->data : null;
                $centralLicenses = $this->listed($client->get('licenses', ['per_page' => 100]));
                $installs = $this->listed($client->get('installs', ['per_page' => 100]));
                // Where the money went. The figure alone left an operator who
                // saw a renewal refused for want of balance with nowhere to
                // look but Central itself. A Central that cannot answer leaves
                // the rest of the page exactly as it was.
                $balance = ($answerBalance = $client->get('balance'))->ok ? (array) $answerBalance->data : null;
                $movements = $this->listed($client->get('balance/transactions', ['per_page' => 10]))['rows'];
            } else {
                $accountError = $me->error;
            }
        }

        $currency = Currency::getDefault();
        $usdRate = $this->usdRate($currency);
        // No dollar currency on the site: today's market rate, for this page
        // only, and only when there are plans to price.
        $usdRateFetched = $usdRate === null && $currency && $plans ? $this->marketUsdRate($currency) : null;
        $usdRate ??= $usdRateFetched;

        $products = Product::with('pricing')->where('server_type', PanelicaLicenseModule::NAME)->orderBy('id')->get();
        $productsByPlan = $products->groupBy(fn (Product $product) => (string) (($product->config_options ?? [])['package_name'] ?? ''));

        $services = Service::with('client', 'product')
            ->whereHas('product', fn ($q) => $q->where('server_type', PanelicaLicenseModule::NAME))
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        // Which service of this site each license and each install belongs to.
        $all = Service::whereHas('product', fn ($q) => $q->where('server_type', PanelicaLicenseModule::NAME))->get(['id', 'module_data']);
        $serviceByLicense = $all->filter(fn ($s) => ! empty($s->module_data['license_id']))->mapWithKeys(fn ($s) => [$s->module_data['license_id'] => $s->id]);
        $serviceByRef = $all->filter(fn ($s) => ! empty($s->module_data['install_ref']))->mapWithKeys(fn ($s) => [$s->module_data['install_ref'] => $s->id]);

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
            'balance' => $balance,
            'movements' => $movements,
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
            'usdRateFetched' => $usdRateFetched,
            'portal' => PartnerAccount::portalUrl(),
            'topUp' => PartnerAccount::portalUrl('customer/reseller/balance'),
            'becomePartner' => PartnerAccount::centralUrl().'/partnership',
            'keyExample' => PartnerAccount::KEY_EXAMPLE,
            'defaultCentral' => PartnerAccount::DEFAULT_CENTRAL,
            'link' => $link,
            'generalCommand' => 'curl -sSL '.route('panelica-license.setup.general').' | bash',
            'centralLicenses' => $centralLicenses,
            'installs' => $installs,
            'serviceByLicense' => $serviceByLicense,
            'serviceByRef' => $serviceByRef,
        ]);
    }

    /**
     * The partner's install link code at Central (the part after /install/).
     * The command customers and the general link run stays at this site's
     * address; Central keeps older codes working until they are disabled in
     * the partner portal.
     */
    public function updateInstallCode(Request $request)
    {
        $this->guard();
        $validated = $request->validate(['code' => ['required', 'string', 'max:40']]);

        $client = CentralClient::partner();
        if (! $client) {
            return back()->with('error', __('panelica_license.error.no_account'));
        }

        $answer = $client->put('install-link', ['code' => strtolower(trim($validated['code']))]);
        if (! $answer->ok) {
            return back()->withInput()->with('error', __('panelica_license.page.code_refused', ['error' => $answer->error]));
        }

        PartnerAccount::forgetInstallCode();

        return back()->with('success', __('panelica_license.page.code_saved', ['code' => (string) ($answer->data['code'] ?? '')]));
    }

    /** @return array{rows: list<array<string, mixed>>, total: int} */
    private function listed(CentralResponse $answer): array
    {
        if (! $answer->ok) {
            return ['rows' => [], 'total' => 0, 'error' => $answer->error];
        }

        $rows = array_values(array_filter((array) $answer->data, 'is_array'));

        return ['rows' => $rows, 'total' => (int) ($answer->meta['total'] ?? count($rows))];
    }

    /** Switched off under Setup -> Modules: the page is not there. */
    private function guard(): void
    {
        abort_unless(PanelicaLicenseModule::enabled(), 404);
    }

    /** Try the key at Central first; only an active partner account is kept. */
    public function connect(Request $request)
    {
        $this->guard();
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
        $this->guard();
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
        $this->guard();
        $validated = $request->validate([
            'plan_id' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
        ] + $this->termRules());

        $prices = $this->terms($validated);

        // A package nobody priced is unsellable, so at least one term is asked
        // for. Which terms is the operator's: Central sells all twelve.
        if ($prices === []) {
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

        $pricing = $this->pricingRows($prices);

        $product = app(ProductCreator::class)->create([
            'name' => $validated['name'],
            'group_id' => $group->id,
            'type' => 'other',
            'description' => __('panelica_license.page.product_description', ['plan' => (string) ($plan['name'] ?? '')]),
            'pay_type' => 'recurring',
            'auto_setup' => 'payment',
            'server_type' => PanelicaLicenseModule::NAME,
        ], $pricing, (string) $plan['id']);

        $this->saveTerms($product, $prices);

        return back()->with('success', __('panelica_license.page.product_created', ['name' => $product->name]));
    }

    /**
     * Look for installed servers and read licenses back now, not in five
     * minutes. LicenseSync itself: the command behind the schedule is
     * registered for the console only, and is not there in a web request.
     */
    /**
     * Change what a package costs, from this page.
     *
     * The page used to be a first-run screen: a package's name was a link to
     * the ordinary product screen and a price could only be changed there.
     * Clearing a box means "not sold on that term" - the same -1 the product
     * screen writes - so a term can be withdrawn here too; a package with no
     * price at all would be unsellable, so that is refused.
     */
    public function updateProductPrices(Request $request, Product $product)
    {
        $this->guard();

        abort_unless(strtolower((string) $product->server_type) === PanelicaLicenseModule::NAME, 404);

        $validated = $request->validate($this->termRules());
        $prices = $this->terms($validated);

        if ($prices === []) {
            return back()->with('error', __('panelica_license.page.price_needed'));
        }

        foreach ($this->pricingRows($prices) as $currencyId => $row) {
            Pricing::updateOrCreate(
                ['type' => 'product', 'currency_id' => $currencyId, 'rel_id' => $product->id],
                $row,
            );
        }

        $this->saveTerms($product, $prices);

        return back()->with('success', __('panelica_license.page.prices_saved', ['name' => $product->name]));
    }

    /**
     * What each currency's price row should hold: the figures the operator
     * typed for the currency the site sells in, converted by its rate for the
     * others, and -1 ("not sold") where a box was left empty.
     *
     * @param  array<string, mixed>  $prices
     * @return array<int|string, array<string, float>>
     */
    private function pricingRows(array $prices): array
    {
        $rows = [];
        foreach (Currency::all() as $currency) {
            $rate = $currency->is_default ? 1.0 : (float) $currency->rate;
            $row = [];
            foreach (self::SELLABLE_CYCLES as $cycle) {
                $months = BillingCycleHelper::months($cycle);
                $row[$cycle] = isset($prices[$months]) && $rate > 0
                    ? round($prices[$months] * $rate, 2)
                    : -1;
            }
            $rows[$currency->id] = $row;
        }

        return $rows;
    }

    /**
     * What the form may send: a price for each term of 1 to 12 months, in
     * months rather than cycle names, because eight of the twelve have no name.
     *
     * @return array<string, array<int, mixed>>
     */
    private function termRules(): array
    {
        return [
            'term' => ['nullable', 'array'],
            'term.*' => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
        ];
    }

    /**
     * The terms the operator priced, months => price, in the shop's currency.
     * A box left empty is a term that is not sold; a term Central does not
     * sell is dropped rather than refused, so a stale form cannot block a save.
     *
     * @param  array<string, mixed>  $validated
     * @return array<int, float>
     */
    private function terms(array $validated): array
    {
        $prices = [];

        foreach ((array) ($validated['term'] ?? []) as $months => $price) {
            $months = (int) $months;

            if (in_array($months, self::SELLABLE_MONTHS, true) && filled($price)) {
                $prices[$months] = (float) $price;
            }
        }

        ksort($prices);

        return $prices;
    }

    /**
     * Store the terms no cycle is named after, in every currency, and remove
     * the rows for terms the operator cleared - the same "not sold" that a -1
     * means for a named cycle.
     *
     * @param  array<int, float>  $prices
     */
    private function saveTerms(Product $product, array $prices): void
    {
        $named = collect(self::SELLABLE_CYCLES)->map(fn ($c) => BillingCycleHelper::months($c))->all();

        foreach (Currency::all() as $currency) {
            $rate = $currency->is_default ? 1.0 : (float) $currency->rate;

            foreach (self::SELLABLE_MONTHS as $months) {
                if (in_array($months, $named, true)) {
                    continue;
                }

                if (isset($prices[$months]) && $rate > 0) {
                    ProductTerm::updateOrCreate(
                        ['product_id' => $product->id, 'currency_id' => $currency->id, 'months' => $months],
                        ['price' => round($prices[$months] * $rate, 2)],
                    );
                } else {
                    ProductTerm::where('product_id', $product->id)
                        ->where('currency_id', $currency->id)->where('months', $months)->delete();
                }
            }
        }
    }

    public function sync(LicenseSync $sync)
    {
        $this->guard();
        $sync->run();

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

    /**
     * How many units of the default currency one US dollar is at today's
     * market rate, from the same providers as pnlcs:currency-update. Kept for
     * a day; a failed lookup is not tried again for an hour, so the page stays
     * quick while the providers are down. Nothing is asked when the operator
     * switched automatic rate updates off.
     */
    private function marketUsdRate(Currency $default): ?float
    {
        if ((string) Setting::get('currency_auto_update', '1') !== '1') {
            return null;
        }

        $key = 'panelica_license.usd_rate.'.strtoupper((string) $default->code);
        $cached = Cache::get($key);
        if ($cached !== null) {
            return (float) $cached > 0 ? (float) $cached : null;
        }

        $rate = app(ExchangeRateService::class)->rate('USD', (string) $default->code, 5);
        Cache::put($key, $rate ?? 0.0, $rate ? now()->addDay() : now()->addHour());

        return $rate;
    }
}
