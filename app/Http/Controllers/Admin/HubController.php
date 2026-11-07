<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Hub\NoticeBoard;
use Illuminate\Http\Request;

/**
 * The admin side of hub.pnlcs.com: closing an info notice, and the one-time
 * box an install that never ran the new wizard is shown on the dashboard.
 */
class HubController extends Controller
{
    public function dismiss(string $notice, NoticeBoard $board)
    {
        $board->dismiss($notice);

        return back();
    }

    /** The two wizard questions, answered (or skipped) from the dashboard. */
    public function answers(Request $request)
    {
        $data = $request->validate([
            'source' => 'nullable|string|max:200',
            'community_profile' => 'nullable|boolean',
            'purpose' => 'nullable|in:live,test',
            'skip' => 'nullable|boolean',
        ]);

        if (! $request->boolean('skip')) {
            Setting::set('hub_source', (string) ($data['source'] ?? ''), 'hub');
            Setting::set('hub_community_profile', $request->boolean('community_profile') ? '1' : '0', 'hub');
            Setting::set('hub_answers_pending', '1', 'hub');

            if (! empty($data['purpose'])) {
                Setting::set('InstallPurpose', $data['purpose'], 'general');
            }
        }

        Setting::set('hub_asked', '1', 'hub');

        return back();
    }
}
