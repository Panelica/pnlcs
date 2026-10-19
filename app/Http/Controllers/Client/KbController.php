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

        $categories = KbCategory::where('hidden', false)
            ->whereNull('parent_id')
            ->with(['articles' => fn ($q) => $q->where('private', false)
                ->when($like, fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('article', 'like', $like)))])
            ->orderBy('sort_order')
            ->get();

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

        return view('client.kb.show', compact('article'));
    }
}
