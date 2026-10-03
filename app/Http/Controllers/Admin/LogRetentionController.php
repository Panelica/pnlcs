<?php

namespace App\Http\Controllers\Admin;

use App\Console\Commands\PruneLogsCommand;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How long each log table is kept, and pruning one now.
 *
 * pnlcs:prune-logs ran nightly with a retention per table, read from
 * settings that no screen could change; nothing showed how big the tables
 * had grown either.
 */
class LogRetentionController extends Controller
{
    public function index()
    {
        $rows = [];
        foreach (PruneLogsCommand::retentionTargets() as $table => [$default, $key]) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $rows[] = [
                'table' => $table,
                'key' => $key,
                'days' => (int) Setting::get($key, (string) $default),
                'default' => $default,
                'rows' => DB::table($table)->count(),
                'oldest' => DB::table($table)->min('created_at'),
            ];
        }

        return view('admin.settings.log-retention', compact('rows'));
    }

    public function update(Request $request)
    {
        $targets = PruneLogsCommand::retentionTargets();
        $v = $request->validate(['days' => 'required|array', 'days.*' => 'required|integer|min:0|max:3650']);

        foreach ($v['days'] as $table => $days) {
            if (isset($targets[$table])) {
                Setting::set($targets[$table][1], (string) (int) $days, 'general');
            }
        }

        return back()->with('success', __('admin.log_retention.saved'));
    }

    /** Apply the retention to one table now, rather than at night. */
    public function prune(Request $request)
    {
        $table = (string) $request->validate(['table' => 'required|string'])['table'];
        abort_unless(array_key_exists($table, PruneLogsCommand::retentionTargets()) && Schema::hasTable($table), 404);

        $before = DB::table($table)->count();
        Artisan::call('pnlcs:prune-logs', ['--table' => $table]);
        $deleted = $before - DB::table($table)->count();

        return back()->with('success', __('admin.log_retention.pruned', ['count' => $deleted]));
    }
}
