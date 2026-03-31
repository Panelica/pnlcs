<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index(Request $request)
    {
        $query = Domain::with("client");
        if ($request->filled("status")) { $query->where("status", $request->status); }
        if ($request->filled("search")) { $query->where("domain", "like", "%{$request->search}%"); }
        $domains = $query->orderBy("created_at", "desc")->paginate(25);
        return view("admin.domains.index", compact("domains"));
    }
}
