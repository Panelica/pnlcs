<?php

namespace App\Http\Controllers;

use App\Models\DomainPricing;
use App\Models\HomepageContent;
use App\Models\HomepageSection;
use App\Models\Product;

class WelcomeController extends Controller
{
    public function index()
    {
        $products = Product::with('pricing', 'group')
            ->where('hidden', false)
            ->where('retired', false)
            ->orderBy('sort_order')
            ->get();

        $domainPricing = DomainPricing::where('enabled', true)
            ->orderBy('sort_order')
            ->take(8)
            ->get();

        $sections = HomepageSection::where('is_enabled', true)
            ->orderBy('sort_order')
            ->get();

        $sectionContent = HomepageContent::all()
            ->groupBy('section_slug')
            ->map(fn($items) => $items->keyBy('content_key'));

        return view('welcome', compact('products', 'domainPricing', 'sections', 'sectionContent'));
    }
}
