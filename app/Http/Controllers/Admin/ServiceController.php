<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::with("client", "product");
        if ($request->filled("status")) { $query->where("status", $request->status); }
        $services = $query->orderBy("created_at", "desc")->paginate(25);
        return view("admin.services.index", compact("services"));
    }

    public function show(Service $service)
    {
        $service->load("client", "product", "server", "addons");
        return view("admin.services.show", compact("service"));
    }
}
