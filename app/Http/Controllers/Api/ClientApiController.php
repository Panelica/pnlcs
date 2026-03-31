<?php
namespace App\Http\Controllers\Api;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientApiController extends BaseApiController
{
    public function getClients(Request $request)
    {
        $query = Client::query();
        if ($request->filled("search")) { $query->search($request->search); }
        if ($request->filled("status")) { $query->where("status", $request->status); }
        if ($request->filled("group_id")) { $query->where("group_id", $request->group_id); }
        $clients = $query->orderBy($request->get("orderby", "id"), $request->get("order", "asc"))
            ->paginate($request->get("limitnum", 25));
        return $this->paginated($clients);
    }

    public function getClientsDetails(Request $request)
    {
        $client = Client::with("contacts")->find($request->clientid);
        if (!$client) return $this->error("Client Not Found", 404);
        return $this->success(["client" => $client->toArray()]);
    }

    public function addClient(Request $request)
    {
        $validated = $request->validate([
            "firstname" => "required|string|max:255",
            "lastname" => "required|string|max:255",
            "email" => "required|email|max:255",
            "companyname" => "nullable|string|max:255",
            "address1" => "nullable|string|max:255",
            "city" => "nullable|string|max:255",
            "state" => "nullable|string|max:255",
            "postcode" => "nullable|string|max:20",
            "country" => "nullable|string|max:2",
            "phonenumber" => "nullable|string|max:30",
        ]);
        $client = Client::create([
            "first_name" => $validated["firstname"],
            "last_name" => $validated["lastname"],
            "email" => $validated["email"],
            "company_name" => $validated["companyname"] ?? null,
            "address1" => $validated["address1"] ?? null,
            "city" => $validated["city"] ?? null,
            "state" => $validated["state"] ?? null,
            "postcode" => $validated["postcode"] ?? null,
            "country" => $validated["country"] ?? "US",
            "phone_number" => $validated["phonenumber"] ?? null,
        ]);
        return $this->success(["clientid" => $client->id]);
    }

    public function updateClient(Request $request)
    {
        $client = Client::find($request->clientid);
        if (!$client) return $this->error("Client Not Found", 404);
        $fields = ["first_name" => "firstname", "last_name" => "lastname", "email" => "email", "company_name" => "companyname", "address1" => "address1", "city" => "city", "state" => "state", "postcode" => "postcode", "country" => "country", "phone_number" => "phonenumber", "status" => "status"];
        foreach ($fields as $dbField => $apiField) {
            if ($request->has($apiField)) $client->$dbField = $request->$apiField;
        }
        $client->save();
        return $this->success(["clientid" => $client->id]);
    }

    public function deleteClient(Request $request)
    {
        $client = Client::find($request->clientid);
        if (!$client) return $this->error("Client Not Found", 404);
        $client->delete();
        return $this->success(["clientid" => $request->clientid]);
    }
}
