<?php
namespace App\Http\Controllers\Client;
use App\Http\Controllers\Controller;
use App\Models\KbCategory;
use App\Models\KbArticle;

class KbController extends Controller
{
    public function index() {
        $categories = KbCategory::where("hidden", false)->whereNull("parent_id")->with("articles")->orderBy("sort_order")->get();
        return view("client.kb.index", compact("categories"));
    }
    public function show(KbArticle $article) {
        $article->increment("views");
        return view("client.kb.show", compact("article"));
    }
}
