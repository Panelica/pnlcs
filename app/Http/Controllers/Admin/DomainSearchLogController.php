<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\WhoisLog;
use Illuminate\Http\Request;

/**
 * What visitors searched for on the storefront: totals, the extensions they
 * ask about, the free names nobody went on to buy, and the latest searches.
 */
class DomainSearchLogController extends Controller
{
    public function index(Request $request)
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;
        $since = now()->subDays($days);

        $rows = WhoisLog::where('created_at', '>=', $since)->latest('id')->limit(20000)->get(['domain', 'available', 'client_id', 'created_at']);

        $extensions = $rows->countBy(fn ($r) => '.'.implode('.', array_slice(explode('.', $r->domain), 1)))->sortDesc()->take(10);

        // Free when searched, and still not ordered by anyone: the sales that got away.
        $freeNames = $rows->where('available', true)->countBy('domain');
        $bought = $freeNames->isEmpty() ? [] : Domain::whereIn('domain', $freeNames->keys())->pluck('domain')->map(fn ($d) => strtolower($d))->all();
        $missed = $freeNames->reject(fn ($n, $name) => in_array($name, $bought, true))->sortDesc()->take(50);

        return view('admin.domains.searches', [
            'days' => $days,
            'total' => $rows->count(),
            'distinct' => $rows->pluck('domain')->unique()->count(),
            'free' => $rows->where('available', true)->count(),
            'taken' => $rows->where('available', false)->count(),
            'unanswered' => $rows->whereNull('available')->count(),
            'extensions' => $extensions,
            'missed' => $missed,
            'recent' => $rows->take(100),
            'enabled' => (string) \App\Models\Setting::get('DomainSearchLog', '1') === '1',
        ]);
    }
}
