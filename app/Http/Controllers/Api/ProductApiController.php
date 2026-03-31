<?php
namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\ProductGroup;

class ProductApiController extends BaseApiController
{
    public function getProducts()
    {
        $products = Product::with("group")->active()->orderBy("sort_order")->get();
        return $this->success(["products" => $products->toArray()]);
    }

    public function getProductGroups()
    {
        $groups = ProductGroup::with("products")->where("hidden", false)->orderBy("sort_order")->get();
        return $this->success(["groups" => $groups->toArray()]);
    }
}
