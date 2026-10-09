<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\KbArticle;
use App\Models\Setting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * /sitemap.xml and /robots.txt.
 *
 * The sitemap lists the pages a visitor can open without an account: the home
 * page, the shop, domain search and prices, the knowledge base and its public
 * articles, the published announcements, the legal documents in force, and the
 * about and guide pages. Addons add their own public pages with the SitemapUrls
 * hook: a callback returns addresses, as strings or ['loc' => ..., 'lastmod' => ...].
 *
 * robots.txt is the operator's text (Setup > General > Search engines and
 * sharing), with the sitemap's address added.
 */
class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [];
        $add = function (string $loc, $lastmod = null) use (&$urls) {
            $urls[$loc] ??= ['loc' => $loc, 'lastmod' => $lastmod ? date('Y-m-d', is_numeric($lastmod) ? (int) $lastmod : strtotime((string) $lastmod)) : null];
        };

        $add(route('home'));
        foreach (['client.store', 'client.domain.search', 'client.domain.pricing', 'client.announcements.index', 'client.contact', 'pages.about', 'pages.ssl', 'pages.mail-setup', 'legal.index'] as $name) {
            if (Route::has($name)) {
                $add(route($name));
            }
        }

        foreach (array_keys(LegalController::published()) as $document) {
            $add(route('legal.show', $document));
        }

        if (kb_enabled()) {
            $add(route('client.kb.index'));
            KbArticle::where('private', false)
                ->whereHas('category', fn ($q) => $q->where('hidden', false))
                ->orderBy('id')
                ->get(['id', 'updated_at'])
                ->each(fn ($a) => $add(route('client.kb.show', $a), $a->updated_at));
        }

        Announcement::where('published', true)->orderByDesc('id')->get(['id', 'updated_at'])
            ->each(fn ($a) => $add(route('client.announcements.show', $a), $a->updated_at));

        foreach (run_hook('SitemapUrls') as $list) {
            foreach ((array) $list as $entry) {
                $loc = is_array($entry) ? ($entry['loc'] ?? null) : $entry;
                if (is_string($loc) && preg_match('#^https?://#i', $loc)) {
                    $add($loc, is_array($entry) ? ($entry['lastmod'] ?? null) : null);
                }
            }
        }

        return response()->view('sitemap', ['urls' => array_values($urls)])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        try {
            $text = trim((string) Setting::get('RobotsTxt', ''));
        } catch (Throwable) {
            $text = '';
        }
        if ($text === '') {
            $text = "User-agent: *\nDisallow:";
        }

        return response($text."\n\nSitemap: ".route('sitemap')."\n", 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
