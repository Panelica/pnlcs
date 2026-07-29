<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\KbCategory;

class KbController extends Controller
{
    /**
     * The knowledge base is public: no login, no client account. Articles
     * carry a published switch in the admin screen, stored inverted as
     * `private`, and nothing here used to look at it — an article taken down,
     * or one still being written, stayed readable to anyone with the URL.
     */
    public function index()
    {
        $categories = KbCategory::where('hidden', false)
            ->whereNull('parent_id')
            ->with(['articles' => fn ($q) => $q->where('private', false)])
            ->orderBy('sort_order')
            ->get();

        return view('client.kb.index', compact('categories'));
    }

    public function show(KbArticle $article)
    {
        abort_if((bool) $article->private, 404);

        $article->increment('views');

        return view('client.kb.show', compact('article'));
    }
}
