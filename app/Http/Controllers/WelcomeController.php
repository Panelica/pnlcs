<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\DockerApp;
use App\Models\DomainPricing;
use App\Models\HomepageContent;
use App\Models\HomepageSection;
use App\Models\Product;
use App\Models\Server;
use App\Services\Module\ModuleRegistry;

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
            ->map(fn ($items) => $items->keyBy('content_key'));

        $currency = Currency::getDefault();

        // The app showcase costs a call to the panel, so it is only read when
        // that section is actually switched on.
        $apps = $sections->contains(fn ($s) => $s->slug === 'docker-apps' && $s->is_enabled)
            ? $this->showcaseApps()
            : [];

        return view('welcome', compact('products', 'domainPricing', 'sections', 'sectionContent', 'currency', 'apps'));
    }

    /**
     * The apps to put in the shop window.
     *
     * Featured first, then the operator's order - the same arrangement the
     * order form uses, so what a visitor is shown is what they will choose
     * from. Anything that goes wrong answers with an empty list and the
     * section simply does not draw.
     *
     * @return array<int, array<string, mixed>>
     */
    private function showcaseApps(): array
    {
        $server = Server::where('type', 'panelica')->where('active', true)->first();
        if (! $server) {
            return [];
        }
        try {
            $module = app(ModuleRegistry::class)->getServerModule('panelica');
            if (! $module || ! method_exists($module, 'appTemplates')) {
                return [];
            }

            return DockerApp::decorate($module->appTemplates($server), sellableOnly: true);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
