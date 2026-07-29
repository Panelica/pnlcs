<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    public function create()
    {
        $groups = ProductGroup::orderBy('sort_order')->get();
        $currencies = Currency::all();

        return view('admin.products.create', compact('groups', 'currencies'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'group_id' => 'required|exists:product_groups,id',
            'type' => 'required|in:hosting,reseller,vps,ssl,other',
            'description' => 'nullable|string',
            'pay_type' => 'required|in:free,onetime,recurring',
        ]);
        $validated['slug'] = Str::slug($validated['name']);
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

        // Best-effort: load panel plans for the Panelica plan dropdown.
        $panelicaPlans = [];
        $server = Server::where('type', 'panelica')->where('active', true)->first();
        if ($server) {
            try {
                $module = app(ModuleRegistry::class)->getServerModule('panelica');
                if ($module && method_exists($module, 'listPlans')) {
                    $panelicaPlans = $module->listPlans($server);
                }
            } catch (\Throwable $e) {
                $panelicaPlans = [];
            }
        }

        return view('admin.products.edit', compact('product', 'groups', 'currencies', 'pricing', 'panelicaPlans'));
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
            'server_type' => 'nullable|string',
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
