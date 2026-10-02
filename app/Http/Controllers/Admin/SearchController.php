<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminSearch;
use Illuminate\Http\Request;

/** The admin bar's search, answered as JSON for the dropdown under the box. */
class SearchController extends Controller
{
    public function __invoke(Request $request, AdminSearch $search)
    {
        return response()->json([
            'query' => (string) $request->query('q', ''),
            'groups' => $search->search(auth('admin')->user(), (string) $request->query('q', '')),
            'all_clients_url' => route('admin.clients.index', ['search' => (string) $request->query('q', '')]),
        ]);
    }
}
