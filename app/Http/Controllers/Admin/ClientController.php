<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\Currency;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::with("contacts");
        if ($request->filled("search")) { $query->search($request->search); }
        if ($request->filled("status")) { $query->where("status", $request->status); }
        if ($request->filled("group_id")) { $query->where("group_id", $request->group_id); }
        $clients = $query->orderBy("created_at", "desc")->paginate(25);
        $groups = ClientGroup::all();
        return view("admin.clients.index", compact("clients", "groups"));
    }

    public function create()
    {
        $groups = ClientGroup::all();
        $currencies = Currency::all();
        return view("admin.clients.create", compact("groups", "currencies"));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "first_name" => "required|string|max:255",
            "last_name" => "required|string|max:255",
            "email" => "required|email|max:255",
            "company_name" => "nullable|string|max:255",
            "address1" => "nullable|string|max:255",
            "city" => "nullable|string|max:255",
            "state" => "nullable|string|max:255",
            "postcode" => "nullable|string|max:20",
            "country" => "nullable|string|max:2",
            "phone_number" => "nullable|string|max:30",
            "status" => "required|in:active,inactive,closed",
            "group_id" => "nullable|exists:client_groups,id",
            "currency_id" => "nullable|exists:currencies,id",
        ]);
        $client = Client::create($validated);
        return redirect()->route("admin.clients.show", $client)->with("success", "Client created successfully.");
    }

    public function show(Client $client)
    {
        $client->load("contacts", "users");
        return view("admin.clients.show", compact("client"));
    }

    public function edit(Client $client)
    {
        $groups = ClientGroup::all();
        $currencies = Currency::all();
        return view("admin.clients.edit", compact("client", "groups", "currencies"));
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            "first_name" => "required|string|max:255",
            "last_name" => "required|string|max:255",
            "email" => "required|email|max:255",
            "company_name" => "nullable|string|max:255",
            "address1" => "nullable|string|max:255",
            "city" => "nullable|string|max:255",
            "state" => "nullable|string|max:255",
            "postcode" => "nullable|string|max:20",
            "country" => "nullable|string|max:2",
            "phone_number" => "nullable|string|max:30",
            "status" => "required|in:active,inactive,closed",
            "group_id" => "nullable|exists:client_groups,id",
            "currency_id" => "nullable|exists:currencies,id",
        ]);
        $client->update($validated);
        return redirect()->route("admin.clients.show", $client)->with("success", "Client updated.");
    }

    public function destroy(Client $client)
    {
        $client->delete();
        return redirect()->route("admin.clients.index")->with("success", "Client deleted.");
    }
}
