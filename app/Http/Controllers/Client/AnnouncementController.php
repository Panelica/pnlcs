<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Announcement;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::where('published', true)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('client.announcements.index', compact('announcements'));
    }

    public function show(Announcement $announcement)
    {
        abort_if(! $announcement->published, 404);

        return view('client.announcements.show', compact('announcement'));
    }
}
