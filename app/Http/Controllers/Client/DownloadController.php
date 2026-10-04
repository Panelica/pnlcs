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
        // Who took what, and when: for a paid file this is what answers a
        // dispute ("I never downloaded it").
        \App\Models\ActivityLog::log("Downloaded \"{$download->title}\" (download #{$download->id})", auth()->user()?->email, $this->getClientId());

        // An uploaded file is handed over here, after the check above; its
        // path is never shown. A link still sends the customer to its address.
        if ($download->isStoredFile()) {
            $disk = \Illuminate\Support\Facades\Storage::disk(Download::DISK);
            abort_unless($disk->exists((string) $download->location), 404);

            return $disk->download((string) $download->location, $download->fileName());
        }

        return redirect($download->location);
    }
}
