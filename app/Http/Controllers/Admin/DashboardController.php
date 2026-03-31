<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Client;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'totalClients' => Client::count(),
            'activeClients' => Client::where('status', 'active')->count(),
            'totalAdmins' => Admin::count(),
        ]);
    }
}
