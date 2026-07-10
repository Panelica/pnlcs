<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\NetworkIssue;

class NetworkStatusController extends Controller
{
    public function index()
    {
        $activeIssues = NetworkIssue::where('status', '!=', 'resolved')
            ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderByDesc('id')
            ->get();

        $resolvedIssues = NetworkIssue::where('status', 'resolved')
            ->orderByDesc('end_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('client.network-status.index', compact('activeIssues', 'resolvedIssues'));
    }
}
