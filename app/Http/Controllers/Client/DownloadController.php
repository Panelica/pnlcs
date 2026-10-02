<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Models\DownloadCategory;

class DownloadController extends Controller
{
    use ResolvesClient;

    public function index()
    {
        $clientId = $this->getClientId();
        $categories = DownloadCategory::with(['downloads' => function ($q) use ($clientId) {
            $q->availableTo($clientId)->orderBy('title');
        }])->get();

        return view('client.downloads.index', compact('categories'));
    }

    public function download(Download $download)
    {
        // A download kept to a product's owners is not there for anyone else:
        // 404, as for one that is not published.
        abort_unless(Download::whereKey($download->id)->availableTo($this->getClientId())->exists(), 404);

        $download->increment('download_count');

        return redirect($download->location);
    }
}
