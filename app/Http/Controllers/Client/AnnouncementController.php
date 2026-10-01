<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $categories = Announcement::publishedCategories();
        $category = in_array($request->query('category'), $categories, true) ? $request->query('category') : null;

        $announcements = Announcement::where('published', true)
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('client.announcements.index', compact('announcements', 'categories', 'category'));
    }

    public function show(Announcement $announcement)
    {
        abort_if(! $announcement->published, 404);

        return view('client.announcements.show', compact('announcement'));
    }

    /**
     * The published announcements as an RSS 2.0 feed, newest first, so
     * customers and status tools can follow maintenance and product news
     * without visiting the site.
     */
    public function rss()
    {
        $items = Announcement::where('published', true)->orderBy('created_at', 'desc')->limit(20)->get();
        $company = (string) Setting::get('CompanyName', config('app.name'));

        $esc = fn (?string $text) => htmlspecialchars((string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<rss version="2.0"><channel>'
            .'<title>'.$esc($company.' - '.__('client.announcements.title')).'</title>'
            .'<link>'.$esc(route('client.announcements.index')).'</link>'
            .'<description>'.$esc(__('client.announcements.page_subtitle')).'</description>'
            .'<language>'.$esc(str_replace('_', '-', app()->getLocale())).'</language>';

        foreach ($items as $item) {
            $link = route('client.announcements.show', $item);
            $xml .= '<item>'
                .'<title>'.$esc($item->title).'</title>'
                .'<link>'.$esc($link).'</link>'
                .'<guid isPermaLink="true">'.$esc($link).'</guid>'
                .'<pubDate>'.$esc($item->created_at?->toRfc2822String()).'</pubDate>'
                .($item->category ? '<category>'.$esc($item->category).'</category>' : '')
                .'<description>'.$esc(Str::limit(trim(strip_tags($item->announcement)), 500)).'</description>'
                .'</item>';
        }

        $xml .= '</channel></rss>';

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
}
