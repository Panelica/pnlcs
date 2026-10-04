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

        // DownloadRequested lets an addon act on the hand-over: refuse it
        // (['abort' => 'reason'], e.g. a licence that is no longer valid) or
        // give this customer their own copy (['path' => '/absolute/file'],
        // e.g. one stamped with their licence). Nothing returned, nothing
        // changes.
        $override = null;
        foreach (run_hook('DownloadRequested', ['download' => $download, 'clientId' => $this->getClientId(), 'user' => auth()->user()]) as $answer) {
            if (! is_array($answer)) {
                continue;
            }
            if (filled($answer['abort'] ?? null)) {
                abort(403, (string) $answer['abort']);
            }
            if (filled($answer['path'] ?? null) && is_file((string) $answer['path']) && is_readable((string) $answer['path'])) {
                $override = ['path' => (string) $answer['path'], 'name' => (string) ($answer['name'] ?? basename((string) $answer['path']))];
            }
        }

        $download->increment('download_count');
        // Who took what, and when: for a paid file this is what answers a
        // dispute ("I never downloaded it").
        \App\Models\ActivityLog::log("Downloaded \"{$download->title}\" (download #{$download->id})", auth()->user()?->email, $this->getClientId());

        // An uploaded file is handed over here, after the check above; its
        // path is never shown. A link still sends the customer to its address.
        if ($override !== null) {
            return response()->download($override['path'], $override['name']);
        }

        if ($download->isStoredFile()) {
            $disk = \Illuminate\Support\Facades\Storage::disk(Download::DISK);
            abort_unless($disk->exists((string) $download->location), 404);

            return $disk->download((string) $download->location, $download->fileName());
        }

        return redirect($download->location);
    }
}
