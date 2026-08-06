<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    /** The columns the list screen offers to sort by. */
    private const SORTABLE = ['created_at', 'expiry_date', 'registration_date', 'domain'];

    public function index(Request $request)
    {
        $query = Domain::with('client');
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('registrar')) {
            $query->where('registrar', $request->registrar);
        }
        if ($request->filled('search')) {
            $query->where('domain', 'like', "%{$request->search}%");
        }

        // Only the columns the screen offers. Anything else used to be handed
        // to the query builder as written, and a name that is not a column
        // came back from the database as an error the visitor saw as a broken
        // page.
        $sortField = in_array($request->get('sort'), self::SORTABLE, true)
            ? $request->get('sort')
            : 'created_at';
        $sortDir = strtolower((string) $request->get('dir')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['domain', 'expiry_date', 'registration_date', 'next_due_date', 'created_at', 'status'];
        if (! in_array($sortField, $allowedSorts)) {
            $sortField = 'created_at';
        }

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
