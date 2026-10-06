<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\Updates\Installation;
use App\Services\Updates\ReleaseIndex;
use App\Services\Updates\UpdatePlan;
use App\Services\Updates\UpdateRunner;
use App\Services\Updates\UpdateState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

/**
 * Setup -> Updates. Shows what is installed and what is published, and asks
 * for a check or an update; the work itself runs from the scheduler
 * (`pnlcs:update --from-request`), never inside a web request.
 */
class UpdateController extends Controller
{
    public function __construct(private readonly UpdateState $state) {}

    public function index(Installation $installation, UpdateRunner $runner)
    {
        $latest = $this->state->read('latest.json');
        $report = $this->state->read('report.json');
        $target = $latest['latest']['version'] ?? null;

        // A report about another release than the one on offer is stale.
        if ($report && $target && ($report['to'] ?? null) !== $target) {
            $report = null;
        }

        return view('admin.config.updates', [
            'installed' => (string) ($installation->version() ?? '-'),
            'mode' => $installation->mode(),
            'channel' => Setting::get('update_channel', ReleaseIndex::STABLE),
            'latest' => $latest,
            'report' => $report,
            'choices' => $target ? ($this->state->read("resolutions/{$target}.json") ?? []) : [],
            'status' => $this->state->read('status.json'),
            'request' => $this->state->read('request.json'),
            'running' => $this->state->isLocked(),
            'unfinished' => $runner->unfinished(),
            'history' => array_slice($this->state->history(), 0, 20),
        ]);
    }

    public function channel(Request $request)
    {
        $validated = $request->validate(['channel' => ['required', Rule::in([ReleaseIndex::STABLE, ReleaseIndex::BETA])]]);

        Setting::set('update_channel', $validated['channel'], 'updates');
        ActivityLog::log('Update channel set to '.$validated['channel'], auth('admin')->user()?->username);
        Artisan::call('pnlcs:update-check');

        return back()->with('success', __('admin.updates.channel_saved'));
    }

    public function check()
    {
        Artisan::call('pnlcs:update-check');
        $latest = $this->state->read('latest.json');

        return isset($latest['error'])
            ? back()->with('error', __('admin.updates.check_failed', ['error' => $latest['error']]))
            : back();
    }

    public function prepare()
    {
        return $this->request('prepare');
    }

    public function apply(Request $request)
    {
        return $this->request('apply', ['allow_major' => $request->boolean('allow_major')]);
    }

    /**
     * The operator's decision on each conflict: the new version, theirs, or a
     * file they merged by hand. Kept per release, used by the next check and
     * by the update.
     */
    public function resolve(Request $request)
    {
        $version = $this->state->read('latest.json')['latest']['version'] ?? null;
        $conflicts = array_column($this->state->read('report.json')['conflicts'] ?? [], 'path');
        abort_unless($version && $conflicts, 404);

        $request->validate([
            'choice' => 'array',
            'choice.*' => ['nullable', Rule::in(['', UpdatePlan::TAKE_NEW, UpdatePlan::KEEP_MINE, 'edited'])],
            'file' => 'array',
            'file.*' => 'nullable|file|max:5120',
        ]);

        foreach ($conflicts as $i => $path) {
            $choice = (string) $request->input("choice.{$i}", '');
            $upload = $request->file("file.{$i}");

            if ($choice === 'edited' && $upload) {
                $this->state->resolve($version, $path, 'edited', (string) file_get_contents($upload->getRealPath()));
            } elseif (in_array($choice, [UpdatePlan::TAKE_NEW, UpdatePlan::KEEP_MINE], true)) {
                $this->state->resolve($version, $path, $choice);
            } elseif ($choice === '') {
                $this->state->clearResolution($version, $path);
            }
        }

        ActivityLog::log("Update {$version}: conflict decisions saved", auth('admin')->user()?->username);

        return back()->with('success', __('admin.updates.choices_saved'));
    }

    /** Both versions of a conflicting file merged with markers, to edit and upload back. */
    public function merged(Request $request)
    {
        $version = $this->state->read('latest.json')['latest']['version'] ?? null;
        $path = (string) $request->query('path');
        $conflicts = array_column($this->state->read('report.json')['conflicts'] ?? [], 'path');
        abort_unless($version && in_array($path, $conflicts, true), 404);
        UpdateState::assertSafe($version, $path);

        $file = $this->state->path("preflight/{$version}/{$path}");
        abort_unless(is_file($file), 404);

        return response()->download($file, basename($path), ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function status()
    {
        // After an update PHP-FPM may still hold the old compiled code; this
        // request runs inside FPM, so it can clear it (UpdateRunner leaves the flag).
        $flag = $this->state->path('opcache-reset-pending');
        if (is_file($flag) && function_exists('opcache_reset')) {
            opcache_reset();
            @unlink($flag);
        }

        return response()->json([
            'status' => $this->state->read('status.json'),
            'request' => $this->state->read('request.json'),
            'running' => $this->state->isLocked(),
        ]);
    }

    private function request(string $action, array $extra = [])
    {
        if ($this->state->isLocked() || $this->state->read('request.json')) {
            return back()->with('error', __('admin.updates.busy'));
        }

        $version = $this->state->read('latest.json')['latest']['version'] ?? null;
        abort_unless($version, 404);

        $admin = auth('admin')->user();
        $this->state->write('request.json', [
            'action' => $action,
            'version' => $version,
            'by' => $admin ? "{$admin->username} (admin area)" : 'admin area',
            'requested_at' => now()->toIso8601String(),
        ] + $extra);
        $this->state->status('queued', $action === 'apply' ? 'maintenance' : 'download', ['version' => $version]);

        ActivityLog::log("Update {$version}: {$action} requested", $admin?->username);

        return back()->with('success', __('admin.updates.queued'));
    }
}
