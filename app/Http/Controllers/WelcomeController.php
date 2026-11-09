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
use Illuminate\Support\Facades\Cache;

class WelcomeController extends Controller
{
    private const SHOWCASE_CACHE_SECONDS = 300;

    private const SHOWCASE_RETRY_SECONDS = 60;

    public function index()
    {
        $products = Product::with('pricing', 'group', 'translations')
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
     * The panel's catalogue is held for a few minutes. The front page is the
     * most visited page there is, and asking the panel on every visit made each
     * one wait for that round trip (and, with the panel down, for its timeout).
     * A failed read is remembered for a minute only, so a panel that comes back
     * is shown again soon. The operator's own choices - featured, order,
     * selling line, not sold - are applied after the cache, so a change in the
     * admin screen shows at once.
     *
     * @return array<int, array<string, mixed>>
     */
    private function showcaseApps(): array
    {
        $server = Server::where('type', 'panelica')->where('active', true)->first();
        if (! $server) {
            return [];
        }

        // Editing the server (address, keys) changes updated_at, so the next
        // visit reads the catalogue afresh instead of serving the old panel's.
        $key = 'showcase-apps:'.$server->id.':'.optional($server->updated_at)->timestamp;
        $templates = Cache::get($key);
        if (! is_array($templates)) {
            $templates = $this->panelCatalogue($server);
            Cache::put($key, $templates, $templates === [] ? self::SHOWCASE_RETRY_SECONDS : self::SHOWCASE_CACHE_SECONDS);
        }

        try {
            return DockerApp::decorate($templates, sellableOnly: true);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function panelCatalogue(Server $server): array
    {
        try {
            $module = app(ModuleRegistry::class)->getServerModule('panelica');
            if (! $module || ! method_exists($module, 'appTemplates')) {
                return [];
            }

            return $module->appTemplates($server);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
