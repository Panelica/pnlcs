<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        $groups = ProductGroup::with('products')->orderBy('sort_order')->get();

        return view('admin.products.index', compact('groups'));
    }

    public function createGroup()
    {
        return view('admin.products.create-group');
    }

    public function storeGroup(Request $request)
    {
        $validated = $request->validate(['name' => 'required|string|max:255', 'headline' => 'nullable|string', 'tagline' => 'nullable|string']);
        $validated['slug'] = Str::slug($validated['name']);
        ProductGroup::create($validated);

        return redirect()->route('admin.products.index')->with('success', __('admin.messages.product_group_created'));
    }

    /**
     * The plans a module's server offers, for the product form.
     *
     * Asked of the first active server of that type - the plans are the
     * panel's, not ours. Anything that goes wrong answers with an empty list
     * and a reason, so the form can say why instead of showing an empty box.
     *
     * @return array{packages: array<int, array{id: string, name: string}>, error: ?string}
     */
    /**
     * @return array<string, mixed>
     */
    private function productConfig(Product $product): array
    {
        $config = is_string($product->config_options)
            ? json_decode($product->config_options, true)
            : ($product->config_options ?? []);

        return is_array($config) ? $config : [];
    }

    private function packagesFor(?string $moduleType): array
    {
        $moduleType = trim((string) $moduleType);

        if ($moduleType === '') {
            return ['packages' => [], 'error' => null];
        }

        $server = Server::where('type', $moduleType)->where('active', true)->first();

        if (! $server) {
            return ['packages' => [], 'error' => __('admin.products.no_server_for_module')];
        }

        $module = app(ModuleRegistry::class)->getServerModule($moduleType);

        if (! $module || ! method_exists($module, 'listPackages')) {
            return ['packages' => [], 'error' => __('admin.products.module_lists_no_packages')];
        }

        try {
            $packages = $module->listPackages($server);
        } catch (\Throwable $e) {
            return ['packages' => [], 'error' => __('admin.products.package_list_failed', ['error' => $e->getMessage()])];
        }

        if ($packages === []) {
            return ['packages' => [], 'error' => __('admin.products.package_list_empty', ['host' => $server->hostname ?: $server->ip_address])];
        }

        return ['packages' => $packages, 'error' => null];
    }

    /**
     * Live lookup for the form when the module is changed.
     */
    public function packages(Request $request)
    {
        return response()->json($this->packagesFor($request->query('module')));
    }

    public function create()
    {
        $groups = ProductGroup::orderBy('sort_order')->get();
        $currencies = Currency::all();

        return view('admin.products.create', [
            'groups' => $groups,
            'currencies' => $currencies,
            // Without these the form could not say how the product is set up,
            // and a product with no module is sold and never provisioned.
            'serverModules' => app(ModuleRegistry::class)->serverModuleNames(),
            'serverGroups' => ServerGroup::orderBy('name')->get(),
            'packageList' => ['packages' => [], 'error' => null],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'group_id' => 'required|exists:product_groups,id',
            'type' => 'required|in:hosting,reseller,vps,ssl,other',
            'description' => 'nullable|string',
            'pay_type' => 'required|in:free,onetime,recurring',
            'auto_setup' => 'nullable|in:order,payment,manual',
            'server_type' => ['nullable', Rule::in(array_keys(app(ModuleRegistry::class)->serverModuleNames()))],
            'server_group_id' => 'nullable|exists:server_groups,id',
        ]);
        $validated['slug'] = Str::slug($validated['name']);

        // The plan lives on the panel; the product records which one it sells.
        if ($request->filled('package_name')) {
            $validated['config_options'] = json_encode(['package_name' => $request->input('package_name')]);
        }

        $product = Product::create($validated);

        // Create default pricing for each currency
        foreach (Currency::all() as $currency) {
            Pricing::create([
                'type' => 'product',
                'currency_id' => $currency->id,
                'rel_id' => $product->id,
                'monthly' => $request->input("pricing.{$currency->id}.monthly", -1),
                'quarterly' => $request->input("pricing.{$currency->id}.quarterly", -1),
                'semiannually' => $request->input("pricing.{$currency->id}.semiannually", -1),
                'annually' => $request->input("pricing.{$currency->id}.annually", -1),
                'biennially' => $request->input("pricing.{$currency->id}.biennially", -1),
                'triennially' => $request->input("pricing.{$currency->id}.triennially", -1),
            ]);
        }

        return redirect()->route('admin.products.edit', $product)->with('success', __('admin.messages.product_created'));
    }

    public function edit(Product $product)
    {
        $groups = ProductGroup::orderBy('sort_order')->get();
        $currencies = Currency::all();
        $pricing = Pricing::where('type', 'product')->where('rel_id', $product->id)->get()->keyBy('currency_id');

        // Best-effort: load panel plans for the Panelica plan dropdown, and the
        // app catalogue for the App Hosting dropdown. Both fall back to a plain
        // text field in the view when the panel cannot be reached.
        $panelicaPlans = [];
        $panelicaTemplates = [];
        $server = Server::where('type', 'panelica')->where('active', true)->first();
        if ($server) {
            $module = null;
            try {
                $module = app(ModuleRegistry::class)->getServerModule('panelica');
            } catch (\Throwable $e) {
                $module = null;
            }
            if ($module && method_exists($module, 'listPlans')) {
                try {
                    $panelicaPlans = $module->listPlans($server);
                } catch (\Throwable $e) {
                    $panelicaPlans = [];
                }
            }
            if ($module && method_exists($module, 'appTemplates')) {
                try {
                    $panelicaTemplates = $module->appTemplates($server);
                } catch (\Throwable $e) {
                    $panelicaTemplates = [];
                }
            }
        }

        return view('admin.products.edit', [
            'product' => $product,
            'groups' => $groups,
            'currencies' => $currencies,
            'pricing' => $pricing,
            'panelicaPlans' => $panelicaPlans,
            'panelicaTemplates' => $panelicaTemplates,
            'serverModules' => app(ModuleRegistry::class)->serverModuleNames(),
            'serverGroups' => ServerGroup::orderBy('name')->get(),
            'packageList' => $this->packagesFor($product->server_type),
            'selectedPackage' => (string) ($this->productConfig($product)['package_name']
                ?? $this->productConfig($product)['panelica_plan_id']
                ?? $this->productConfig($product)['cpanel_package']
                ?? ''),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'group_id' => 'required|exists:product_groups,id',
            'type' => 'required|in:hosting,reseller,vps,ssl,other',
            'description' => 'nullable|string',
            'pay_type' => 'required|in:free,onetime,recurring',
            'hidden' => 'nullable|boolean',
            'retired' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'auto_setup' => 'nullable|in:order,payment,manual',
            // Only a module this installation actually has: it used to be free
            // text, so a typo left the product unprovisionable and silent.
            'server_type' => ['nullable', Rule::in(array_keys(app(ModuleRegistry::class)->serverModuleNames()))],
            'server_group_id' => 'nullable|exists:server_groups,id',
            'welcome_email_template' => 'nullable|string',
            'ssl_module' => 'nullable|string|max:100',
            'stock_control' => 'nullable|boolean',
            'stock_qty' => 'nullable|integer|min:0',
        ]);
        $validated['stock_control'] = $request->boolean('stock_control');
        $validated['stock_qty'] = (int) $request->input('stock_qty', 0);
        $validated['hidden'] = $request->boolean('hidden');
        $validated['retired'] = $request->boolean('retired');
        $validated['is_featured'] = $request->boolean('is_featured');
        $product->update($validated);

        // The plan the product sells, in the one key every module reads.
        if ($request->has('package_name')) {
            $config = $this->productConfig($product->fresh());
            $config['package_name'] = (string) $request->input('package_name');
            $product->update(['config_options' => json_encode($config)]);
        }

        // Panelica managed resources -> merged into config_options (preserves
        // feature text f1..f7 and any other existing keys).
        if ($request->boolean('res_section')) {
            $config = is_string($product->config_options)
                ? (json_decode($product->config_options, true) ?: [])
                : ($product->config_options ?? []);
            foreach ([
                'res_disk_mb', 'res_bandwidth_mb', 'res_max_domains', 'res_max_subdomains',
                'res_max_email', 'res_max_db', 'res_max_ftp', 'res_max_cron', 'res_max_containers',
                'res_cpu_percent', 'res_memory_mb', 'res_process_limit', 'res_io_mbs', 'res_iops',
                'res_network_mbit', 'res_inode_quota', 'res_php_memory_mb', 'res_php_exec', 'res_php_upload',
            ] as $k) {
                $v = $request->input($k);
                if ($v !== null && $v !== '') {
                    $config[$k] = (int) $v;
                }
            }
            $config['res_ssh_level'] = $request->input('res_ssh_level', 'none');
            $config['res_quota_mode'] = $request->input('res_quota_mode', 'strict');
            $config['res_modsec'] = $request->input('res_modsec', 'on');
            $config['res_backup'] = $request->input('res_backup', 'on');
            $config['res_managed'] = $request->boolean('res_managed') ? 1 : 0;
            // App Hosting: the app the order installs. Empty means regular hosting.
            $appTpl = strtolower(trim((string) $request->input('panelica_app_template', '')));
            if ($appTpl !== '' && preg_match('/^[a-z0-9][a-z0-9._-]*$/', $appTpl)) {
                $config['panelica_app_template'] = $appTpl;
            } else {
                unset($config['panelica_app_template']);
            }
            $config['panelica_container_plan'] = $request->boolean('panelica_container_plan') ? 1 : 0;
            $config['panelica_app_choose'] = $request->boolean('panelica_app_choose') ? 1 : 0;
            $planId = trim((string) $request->input('panelica_plan_id', ''));
            if ($planId !== '') {
                $config['panelica_plan_id'] = $planId;
            } else {
                unset($config['panelica_plan_id']);
            }
            $product->update(['config_options' => json_encode($config)]);
        }

        // Update pricing
        foreach (Currency::all() as $currency) {
            Pricing::updateOrCreate(
                ['type' => 'product', 'currency_id' => $currency->id, 'rel_id' => $product->id],
                [
                    'monthly_setup' => $request->input("pricing.{$currency->id}.monthly_setup", 0),
                    'quarterly_setup' => $request->input("pricing.{$currency->id}.quarterly_setup", 0),
                    'semiannually_setup' => $request->input("pricing.{$currency->id}.semiannually_setup", 0),
                    'annually_setup' => $request->input("pricing.{$currency->id}.annually_setup", 0),
                    'monthly' => $request->input("pricing.{$currency->id}.monthly", -1),
                    'quarterly' => $request->input("pricing.{$currency->id}.quarterly", -1),
                    'semiannually' => $request->input("pricing.{$currency->id}.semiannually", -1),
                    'annually' => $request->input("pricing.{$currency->id}.annually", -1),
                    'biennially' => $request->input("pricing.{$currency->id}.biennially", -1),
                    'triennially' => $request->input("pricing.{$currency->id}.triennially", -1),
                ]
            );
        }

        return back()->with('success', __('admin.messages.product_updated'));
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', __('admin.messages.product_deleted'));
    }
}
