<?php

namespace App\Http\Controllers;

use App\Models\Product;

class WelcomeController extends Controller
{
    public function index()
    {
        $products = Product::with("pricing", "group")
            ->where("hidden", false)
            ->where("retired", false)
            ->orderBy("sort_order")
            ->get();

        return view("welcome", compact("products"));
    }
}
