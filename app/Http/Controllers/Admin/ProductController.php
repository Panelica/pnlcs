<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Pricing;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index()
    {
        $groups = ProductGroup::with("products")->orderBy("sort_order")->get();
        return view("admin.products.index", compact("groups"));
    }

    public function createGroup()
    {
        return view("admin.products.create-group");
    }

    public function storeGroup(Request $request)
    {
        $validated = $request->validate(["name" => "required|string|max:255", "headline" => "nullable|string", "tagline" => "nullable|string"]);
        $validated["slug"] = Str::slug($validated["name"]);
        ProductGroup::create($validated);
        return redirect()->route("admin.products.index")->with("success", "Product group created.");
    }

    public function create()
    {
        $groups = ProductGroup::orderBy("sort_order")->get();
        $currencies = Currency::all();
        return view("admin.products.create", compact("groups", "currencies"));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "name" => "required|string|max:255",
            "group_id" => "required|exists:product_groups,id",
            "type" => "required|in:hosting,reseller,vps,other",
            "description" => "nullable|string",
            "pay_type" => "required|in:free,onetime,recurring",
        ]);
        $validated["slug"] = Str::slug($validated["name"]);
        $product = Product::create($validated);

        // Create default pricing for each currency
        foreach (Currency::all() as $currency) {
            Pricing::create([
                "type" => "product",
                "currency_id" => $currency->id,
                "rel_id" => $product->id,
                "monthly" => $request->input("pricing.{$currency->id}.monthly", -1),
                "quarterly" => $request->input("pricing.{$currency->id}.quarterly", -1),
                "semiannually" => $request->input("pricing.{$currency->id}.semiannually", -1),
                "annually" => $request->input("pricing.{$currency->id}.annually", -1),
                "biennially" => $request->input("pricing.{$currency->id}.biennially", -1),
                "triennially" => $request->input("pricing.{$currency->id}.triennially", -1),
            ]);
        }

        return redirect()->route("admin.products.edit", $product)->with("success", "Product created.");
    }

    public function edit(Product $product)
    {
        $groups = ProductGroup::orderBy("sort_order")->get();
        $currencies = Currency::all();
        $pricing = Pricing::where("type", "product")->where("rel_id", $product->id)->get()->keyBy("currency_id");
        return view("admin.products.edit", compact("product", "groups", "currencies", "pricing"));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            "name" => "required|string|max:255",
            "group_id" => "required|exists:product_groups,id",
            "type" => "required|in:hosting,reseller,vps,other",
            "description" => "nullable|string",
            "pay_type" => "required|in:free,onetime,recurring",
            "hidden" => "nullable|boolean",
            "retired" => "nullable|boolean",
            "is_featured" => "nullable|boolean",
            "auto_setup" => "nullable|in:order,payment,on,off",
            "server_type" => "nullable|string",
            "welcome_email_template" => "nullable|string",
        ]);
        $validated["hidden"] = $request->boolean("hidden");
        $validated["retired"] = $request->boolean("retired");
        $validated["is_featured"] = $request->boolean("is_featured");
        $product->update($validated);

        // Update pricing
        foreach (Currency::all() as $currency) {
            Pricing::updateOrCreate(
                ["type" => "product", "currency_id" => $currency->id, "rel_id" => $product->id],
                [
                    "monthly_setup" => $request->input("pricing.{$currency->id}.monthly_setup", 0),
                    "quarterly_setup" => $request->input("pricing.{$currency->id}.quarterly_setup", 0),
                    "semiannually_setup" => $request->input("pricing.{$currency->id}.semiannually_setup", 0),
                    "annually_setup" => $request->input("pricing.{$currency->id}.annually_setup", 0),
                    "monthly" => $request->input("pricing.{$currency->id}.monthly", -1),
                    "quarterly" => $request->input("pricing.{$currency->id}.quarterly", -1),
                    "semiannually" => $request->input("pricing.{$currency->id}.semiannually", -1),
                    "annually" => $request->input("pricing.{$currency->id}.annually", -1),
                    "biennially" => $request->input("pricing.{$currency->id}.biennially", -1),
                    "triennially" => $request->input("pricing.{$currency->id}.triennially", -1),
                ]
            );
        }

        return back()->with("success", "Product updated.");
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route("admin.products.index")->with("success", "Product deleted.");
    }
}
