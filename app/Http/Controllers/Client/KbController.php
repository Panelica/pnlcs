<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\KbCategory;

class KbController extends Controller
{
    /**
     * One switch, every door: with the knowledge base turned off in the
     * settings the links are gone from the menus, and a bookmarked or
     * crawled URL gets a 404 rather than an empty article list.
     */
    public function __construct()
    {
        abort_unless(kb_enabled(), 404);
    }

    /**
     * The knowledge base is public: no login, no client account. Articles
     * carry a published switch in the admin screen, stored inverted as
     * `private`, and nothing here used to look at it — an article taken down,
     * or one still being written, stayed readable to anyone with the URL.
     */
    public function index(\Illuminate\Http\Request $request)
    {
        // The page has always had a search box; it sent ?q= and the list
        // came back whole, whatever was typed.
        $searchQuery = trim(mb_substr((string) $request->query('q', ''), 0, 100));
        $like = $searchQuery !== '' ? '%'.addcslashes($searchQuery, '\\%_').'%' : null;
        // A visitor reads the article in their language where it is
        // translated, so the search looks at that text as well.
        $locale = app()->getLocale();
        $matches = fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('article', 'like', $like));

        $categories = KbCategory::where('hidden', false)
            ->whereNull('parent_id')
            ->with(['articles' => fn ($q) => $q->where('private', false)
                ->when($like, fn ($q) => $q->where(fn ($w) => $matches($w)
                    ->orWhereHas('translations', fn ($t) => $matches($t->where('language', $locale))))),
                'articles.translations', 'translations'])
            ->orderBy('sort_order')
            ->get()
            // In the visitor's language where a translation is written.
            ->map(fn (KbCategory $c) => $c->inLocale());

        // A search shows only the categories it found something in.
        if ($like) {
            $categories = $categories->filter(fn ($c) => $c->articles->isNotEmpty())->values();
        }

        return view('client.kb.index', compact('categories', 'searchQuery'));
    }

    public function show(KbArticle $article)
    {
        abort_if((bool) $article->private, 404);

        $article->increment('views');
        $article = $article->load(['translations', 'category.translations'])->inLocale();

        return view('client.kb.show', compact('article'));
    }
}
