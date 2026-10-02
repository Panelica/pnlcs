<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\InvoiceProduct;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\Service;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Models\Setting;
use App\Models\TaxRule;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        $groups = ProductGroup::with('products')->orderBy('sort_order')->get();

        $invoiceProducts = InvoiceProduct::orderBy('name')->get();
        $taxRateOptions = $this->taxRateOptions();
        $defaultTaxLabel = $taxRateOptions->first()?->name ?? '';

        return view('admin.products.index', compact('groups', 'invoiceProducts', 'taxRateOptions', 'defaultTaxLabel'));
    }

    /**
     * The rates offered when pricing a catalog product, narrowed to the
     * company's own country.
     *
     * @return \Illuminate\Support\Collection<int, TaxRule>
     */
    private function taxRateOptions()
    {
        $country = (string) Setting::get('Country', '');

        $options = TaxRule::where('country', $country)
            ->where('state', '')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        if ($options->isEmpty()) {
            $options = TaxRule::orderBy('name')->get();
        }

        return $options->unique('name')->values();
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

    /**
     * Nodes, storages, bridges and templates of a Proxmox server, for the
     * product form's drop-downs. Asked live, so the form offers what the
     * cluster really has rather than names typed from memory.
     */
    public function proxmoxCatalog(Request $request)
    {
        $server = Server::where('type', 'proxmox')
            ->when($request->integer('server_id'), fn ($q, $id) => $q->whereKey($id))
            ->orderByDesc('active')->first();

        if (! $server) {
            return response()->json(['ok' => false, 'error' => __('admin.products.no_server_for_module')]);
        }

        $module = app(ModuleRegistry::class)->getServerModule('proxmox');
        try {
            $catalog = $module->catalog($server, (string) $request->query('node', ''));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }

        return response()->json($catalog + ['server_id' => $server->id]);
    }

    /**
     * Build the order options (operating system, memory, cores, disk) of a
     * Proxmox product from the short form on its page, in one step.
     */
    public function proxmoxOptions(Request $request, Product $product)
    {
        if (strtolower((string) $product->server_type) !== 'proxmox') {
            return response()->json(['success' => false, 'message' => __('proxmox.product.opt_save_first')], 422);
        }

        $request->validate([
            'options' => 'required|array',
            'options.*.title' => 'nullable|string|max:80',
            'options.*.choices' => 'required|array|min:1|max:30',
            'options.*.choices.*.value' => 'required|string|max:255',
            'options.*.choices.*.label' => 'nullable|string|max:80',
            'options.*.choices.*.price' => 'nullable|numeric|min:0|max:100000',
        ]);

        $group = \Modules\Servers\Proxmox\ProxmoxOrderOptions::create($product, (array) $request->input('options'));
        \App\Models\ActivityLog::log("Order options created for Proxmox product #{$product->id} (group #{$group->id})", auth('admin')->user()?->full_name ?: 'admin');

        return response()->json([
            'success' => true,
            'message' => __('proxmox.product.opt_created'),
            'linked' => \Modules\Servers\Proxmox\ProxmoxOrderOptions::linked($product->fresh()),
        ]);
    }

    /**
     * The Proxmox part of the product form, validated and ready to merge into
     * config_options.
     */
    private function proxmoxConfig(Request $request): array
    {
        $v = $request->validate([
            'pve_type' => 'required|in:qemu,lxc',
            'pve_node' => ['nullable', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'pve_template' => 'nullable|integer|min:100',
            'pve_ostemplate' => 'nullable|string|max:255',
            'pve_iso' => 'nullable|string|max:255',
            'pve_storage' => ['required', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'pve_bridge' => ['required', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'pve_vlan' => 'nullable|integer|min:0|max:4094',
            'pve_rate' => 'nullable|numeric|min:0|max:100000',
            'pve_cores' => 'required|integer|min:1|max:512',
            'pve_sockets' => 'nullable|integer|min:1|max:8',
            'pve_cpulimit' => 'nullable|numeric|min:0|max:512',
            'pve_memory' => 'required|integer|min:64',
            'pve_swap' => 'nullable|integer|min:0',
            'pve_disk' => 'required|integer|min:1',
            'pve_bandwidth' => 'nullable|integer|min:0',
            'pve_snapshots' => 'nullable|integer|min:0|max:50',
            'pve_backups' => 'nullable|integer|min:0|max:100',
            'pve_ipv4' => 'required|in:dhcp,pool',
            'pve_ipv6' => 'nullable|in:none,auto',
            'pve_ciuser' => ['nullable', 'regex:/^[a-z_][a-z0-9_-]{0,31}$/'],
            'pve_nameserver' => 'nullable|string|max:255',
            'pve_os_choices' => 'nullable|array',
            'pve_os_choices.*' => 'string|max:255',
        ]);

        if ($v['pve_type'] === 'lxc' && blank($v['pve_ostemplate'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['pve_ostemplate' => __('proxmox.error.no_ostemplate')]);
        }
        if ($v['pve_type'] === 'qemu' && blank($v['pve_template'] ?? null) && blank($v['pve_iso'] ?? null)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['pve_template' => __('proxmox.error.no_template')]);
        }

        $names = (array) $request->input('pve_os_names', []);

        return [
            'pve_type' => $v['pve_type'],
            'pve_node' => (string) ($v['pve_node'] ?? ''),
            'pve_template' => (string) ($v['pve_template'] ?? ''),
            'pve_ostemplate' => (string) ($v['pve_ostemplate'] ?? ''),
            'pve_iso' => (string) ($v['pve_iso'] ?? ''),
            'pve_storage' => $v['pve_storage'],
            'pve_bridge' => $v['pve_bridge'],
            'pve_vlan' => (int) ($v['pve_vlan'] ?? 0),
            'pve_rate' => (float) ($v['pve_rate'] ?? 0),
            'pve_firewall' => $request->boolean('pve_firewall') ? 1 : 0,
            'pve_cores' => (int) $v['pve_cores'],
            'pve_sockets' => (int) ($v['pve_sockets'] ?? 1),
            'pve_cpulimit' => (float) ($v['pve_cpulimit'] ?? 0),
            'pve_memory' => (int) $v['pve_memory'],
            'pve_swap' => (int) ($v['pve_swap'] ?? 512),
            'pve_disk' => (int) $v['pve_disk'],
            'pve_bandwidth' => (int) ($v['pve_bandwidth'] ?? 0),
            'pve_snapshots' => (int) ($v['pve_snapshots'] ?? 0),
            'pve_backups' => (int) ($v['pve_backups'] ?? 0),
            'pve_ipv4' => $v['pve_ipv4'],
            'pve_ipv6' => $v['pve_ipv6'] ?? 'none',
            'pve_nesting' => $request->boolean('pve_nesting') ? 1 : 0,
            'pve_protection' => $request->boolean('pve_protection') ? 1 : 0,
            'pve_ciuser' => (string) ($v['pve_ciuser'] ?? 'root') ?: 'root',
            'pve_ciupgrade' => $request->boolean('pve_ciupgrade') ? 1 : 0,
            'pve_nameserver' => (string) ($v['pve_nameserver'] ?? ''),
            'pve_os_choices' => collect($v['pve_os_choices'] ?? [])
                ->map(fn ($id) => ['id' => (string) $id, 'name' => trim((string) ($names[$id] ?? '')) ?: \Modules\Servers\Proxmox\ProxmoxPlan::imageName((string) $id)])
                ->values()->all(),
        ];
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
        $validated = $request->validate(\App\Services\ProductCreator::rules());

        $product = app(\App\Services\ProductCreator::class)->create(
            $validated,
            (array) $request->input('pricing', []),
            $request->filled('package_name') ? (string) $request->input('package_name') : null,
        );

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
            'sslModules' => app(ModuleRegistry::class)->sslModuleNames(),
            'serverGroups' => ServerGroup::orderBy('name')->get(),
            'packageList' => $this->packagesFor($product->server_type),
            // Upgrade packages: only products the same module provisions,
            // the only moves UpgradeService::canMoveTo() allows anyway.
            'upgradeCandidates' => Product::where('id', '!=', $product->id)
                ->whereRaw('LOWER(COALESCE(server_type, \'\')) = ?', [strtolower((string) $product->server_type)])
                ->orderBy('group_id')->orderBy('name')->get(['id', 'name', 'group_id', 'hidden', 'retired']),
            'selectedUpgrades' => $product->upgradeProducts()->pluck('products.id')->map(fn ($id) => (int) $id)->all(),
            'proxmoxServers' => Server::where('type', 'proxmox')->orderByDesc('active')->orderBy('name')->get(['id', 'name', 'active']),
            'pveOrderOptions' => strtolower((string) $product->server_type) === 'proxmox' ? \Modules\Servers\Proxmox\ProxmoxOrderOptions::linked($product) : [],
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
            'pricing' => 'nullable|array',
            'pricing.*' => 'nullable|array',
            'pricing.*.*' => 'nullable|numeric|min:-1',
            'upgrade_paths' => 'nullable|array',
            'upgrade_paths.*' => 'integer|exists:products,id',
        ]);
        unset($validated['pricing'], $validated['upgrade_paths']);

        // Checked before anything is saved, so a bad Proxmox field does not
        // leave the product half updated.
        $proxmoxConfig = $request->boolean('pve_section') && strtolower((string) ($validated['server_type'] ?? '')) === 'proxmox'
            ? $this->proxmoxConfig($request)
            : null;

        $validated['stock_control'] = $request->boolean('stock_control');
        $validated['stock_qty'] = (int) $request->input('stock_qty', 0);
        $validated['hidden'] = $request->boolean('hidden');
        $validated['retired'] = $request->boolean('retired');
        $validated['is_featured'] = $request->boolean('is_featured');
        $product->update($validated);

        // Upgrade packages, when the form carried the field. Only products of
        // the product's (possibly just changed) module are kept: a path to
        // another module could never be taken.
        if ($request->boolean('upgrade_paths_section')) {
            $module = strtolower((string) $product->fresh()->server_type);
            $ids = Product::whereIn('id', (array) $request->input('upgrade_paths', []))
                ->where('id', '!=', $product->id)
                ->get(['id', 'server_type'])
                ->filter(fn (Product $p) => strtolower((string) $p->server_type) === $module)
                ->pluck('id')->all();
            $product->upgradeProducts()->sync($ids);
        }

        // The plan the product sells, in the one key every module reads.
        if ($request->has('package_name')) {
            $config = $this->productConfig($product->fresh());
            $config['package_name'] = (string) $request->input('package_name');
            $product->update(['config_options' => $config]);
        }

        if ($proxmoxConfig !== null) {
            $product->update(['config_options' => array_merge($this->productConfig($product->fresh()), $proxmoxConfig)]);
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

            // A product that sells container apps while its plan forbids
            // containers is not a configuration, it is a trap: the customer
            // pays, the panel refuses the app, the account is rolled back, and
            // the error surfaces as a cryptic log line three screens away.
            // The form warns about this in grey text; a warning nobody has to
            // read is not a gate. Saved with 0, exactly that happened live.
            $sellsApps = $config['panelica_container_plan'] || $config['panelica_app_choose']
                || ($config['panelica_app_template'] ?? '') !== '';
            $maxContainers = (int) ($config['res_max_containers'] ?? 0);
            if ($sellsApps && $maxContainers === 0) {
                return back()->withInput()
                    ->with('error', __('admin.products.container_plan_needs_containers'));
            }
            $planId = trim((string) $request->input('panelica_plan_id', ''));
            if ($planId !== '') {
                $config['panelica_plan_id'] = $planId;
            } else {
                unset($config['panelica_plan_id']);
            }
            $product->update(['config_options' => $config]);
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
        // services.product_id is a plain column: deleting a product customers
        // still hold leaves their services pointing at nothing - no name on
        // the renewal line, no server module to suspend or terminate with.
        // Retire it instead; delete once every service on it has ended.
        $held = Service::where('product_id', $product->id)
            ->whereNotIn('status', ['terminated', 'cancelled', 'fraud'])
            ->count();

        if ($held > 0) {
            return back()->with('error', __('admin.messages.product_in_use', ['count' => $held]));
        }

        $product->delete();

        return redirect()->route('admin.products.index')->with('success', __('admin.messages.product_deleted'));
    }

    /**
     * Store a catalog product/service (goods or services for invoicing).
     */
    public function storeInvoiceProduct(Request $request)
    {
        $data = $this->validateInvoiceProduct($request);
        InvoiceProduct::create($data);

        return back()->with('success', __('messages.success.product_service_added'));
    }

    public function updateInvoiceProduct(Request $request, InvoiceProduct $invoiceProduct)
    {
        $data = $this->validateInvoiceProduct($request);
        $invoiceProduct->update($data);

        return back()->with('success', __('messages.success.product_service_updated'));
    }

    public function destroyInvoiceProduct(InvoiceProduct $invoiceProduct)
    {
        $invoiceProduct->delete();

        return back()->with('success', __('messages.success.product_service_deleted'));
    }

    /**
     * @return array{name: string, price: string, unit: ?string, tax_rate: float, tax_label: ?string}
     */
    private function validateInvoiceProduct(Request $request): array
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'tax_label' => 'nullable|string|max:255',
        ]);

        $taxLabel = $v['tax_label'] ?: null;
        $taxRate = $taxLabel
            ? (float) (TaxRule::where('name', $taxLabel)->value('tax_rate') ?? 0)
            : 0.0;

        return [
            'name' => $v['name'],
            'price' => $v['price'],
            'unit' => $v['unit'] ?? null,
            'tax_rate' => $taxRate,
            'tax_label' => $taxLabel,
        ];
    }
}
