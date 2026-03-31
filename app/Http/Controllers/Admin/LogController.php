<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Email;
use App\Models\GatewayLog;
use App\Models\ModuleLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogController extends Controller
{
    /**
     * Main logs index — shows tabbed log viewer with activity logs by default.
     */
    public function index(Request $request): View
    {
        $type = $request->get('type', 'activity');

        $query = ActivityLog::query();

        if ($request->filled('user')) {
            $query->where('user', $request->user);
        }
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $logs = $query->orderBy('id', 'desc')->paginate(50);

        return view('admin.logs.index', compact('logs', 'type'));
    }

    /**
     * Gateway payment logs.
     */
    public function gateway(Request $request): View
    {
        $query = GatewayLog::query();

        if ($request->filled('gateway')) {
            $query->where('gateway', $request->gateway);
        }
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        $logs     = $query->orderBy('id', 'desc')->paginate(50);
        $gateways = GatewayLog::select('gateway')->distinct()->orderBy('gateway')->pluck('gateway');

        return view('admin.logs.gateway', compact('logs', 'gateways'));
    }

    /**
     * Module action logs.
     */
    public function module(Request $request): View
    {
        $query = ModuleLog::query();

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        $logs    = $query->orderBy('id', 'desc')->paginate(50);
        $modules = ModuleLog::select('module')->distinct()->orderBy('module')->pluck('module');

        return view('admin.logs.module', compact('logs', 'modules'));
    }

    /**
     * Email delivery logs.
     */
    public function email(Request $request): View
    {
        $query = Email::with('client');

        if ($request->filled('status')) {
            if ($request->status === 'failed') {
                $query->where('failed', true);
            } elseif ($request->status === 'pending') {
                $query->where('pending', true)->where('failed', false);
            } elseif ($request->status === 'sent') {
                $query->where('pending', false)->where('failed', false);
            }
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('subject', 'like', '%' . $request->search . '%')
                  ->orWhere('to', 'like', '%' . $request->search . '%');
            });
        }

        $logs = $query->orderBy('id', 'desc')->paginate(50);

        return view('admin.logs.email', compact('logs'));
    }
}
