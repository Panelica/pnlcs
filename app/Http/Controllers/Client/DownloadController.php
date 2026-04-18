<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Models\DownloadCategory;

class DownloadController extends Controller
{
    public function index()
    {
        $categories = DownloadCategory::with(['downloads' => function ($q) {
            $q->where('hidden', false)->orderBy('title');
        }])->get();

        return view('client.downloads.index', compact('categories'));
    }

    public function download(Download $download)
    {
        abort_if($download->hidden, 404);

        $download->increment('download_count');

        return redirect($download->location);
    }
}
