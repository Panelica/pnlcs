<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index(Request $request)
    {
        $query = Domain::with('client');
        if ($request->filled('status')) { $query->where('status', $request->status); }
        if ($request->filled('registrar')) { $query->where('registrar', $request->registrar); }
        if ($request->filled('search')) { $query->where('domain', 'like', "%{$request->search}%"); }

        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');
        $allowedSorts = ['domain', 'expiry_date', 'registration_date', 'next_due_date', 'created_at', 'status'];
        if (!in_array($sortField, $allowedSorts)) { $sortField = 'created_at'; }

        $domains = $query->orderBy($sortField, $sortDir)->paginate(25);

        $registrars = Domain::distinct()->pluck('registrar')->filter()->sort()->values();
        $statuses = ['active', 'pending', 'grace', 'redemption', 'expired', 'cancelled', 'transferred_away'];

        return view('admin.domains.index', compact('domains', 'registrars', 'statuses'));
    }

    public function show(Domain $domain)
    {
        $domain->load('client', 'order');
        return view('admin.domains.show', compact('domain'));
    }
}
