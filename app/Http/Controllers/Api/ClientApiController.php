<?php

namespace App\Http\Controllers\Api;

use App\Enums\ClientStatus;
use Illuminate\Validation\Rule;
use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\ClientNote;
use App\Models\Contact;
use App\Models\Credit;
use App\Models\User;
use Illuminate\Http\Request;

class ClientApiController extends BaseApiController
{
    /** The columns a caller may order the client list by. */
    private const ORDERABLE = ['id', 'first_name', 'last_name', 'email', 'company_name', 'status', 'created_at'];

    public function getClients(Request $request)
    {
        $query = Client::query();
        if ($request->filled('search')) {
            $query->search($request->search);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        // Same rule as the screens: a caller cannot name a column that is not
        // one, or a direction that is not a direction, and get an error page
        // out of the database.
        $orderBy = in_array($request->get('orderby'), self::ORDERABLE, true)
            ? $request->get('orderby')
            : 'id';
        $order = strtolower((string) $request->get('order')) === 'desc' ? 'desc' : 'asc';

        return $this->paginated($query->orderBy($orderBy, $order)->paginate($this->getPerPage(), ['*'], 'page', $this->getPage()));
    }

    public function getClientsDetails(Request $request)
    {
        $client = Client::with('contacts')->find($request->clientid);
        if (! $client) {
            return $this->error('Client Not Found', 404);
        }

        return $this->success(['client' => $client->toArray()]);
    }

    public function addClient(Request $request)
    {
        // The address a client is found by: signing in, resetting a password,
        // matching an incoming support email. The admin form has always refused
        // one that is taken, and clients.email carries an ordinary index, so
        // nothing else would have stopped a second account on it.
        $validated = $request->validate(['firstname' => 'required|string|max:255', 'lastname' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:clients,email']);
        $client = Client::create(['first_name' => $validated['firstname'], 'last_name' => $validated['lastname'], 'email' => $validated['email'], 'company_name' => $request->companyname, 'address1' => $request->address1, 'city' => $request->city, 'state' => $request->state, 'postcode' => $request->postcode, 'country' => $request->country ?? 'US', 'phone_number' => $request->phonenumber]);

        return $this->success(['clientid' => $client->id]);
    }

    public function updateClient(Request $request)
    {
        $client = Client::find($request->clientid);
        if (! $client) {
            return $this->error('Client Not Found', 404);
        }

        // What the account screens check before writing the same two fields: an
        // address nobody else has, and one of the three statuses. These used to
        // go straight onto the record, so the api could move a client onto an
        // address already in use, or hand the enum cast a status it does not
        // know and turn the call into a 500.
        $request->validate([
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('clients', 'email')->ignore($client->id)],
            'status' => ['sometimes', Rule::enum(ClientStatus::class)],
        ]);

        foreach (['first_name' => 'firstname', 'last_name' => 'lastname', 'email' => 'email', 'company_name' => 'companyname', 'address1' => 'address1', 'city' => 'city', 'state' => 'state', 'postcode' => 'postcode', 'country' => 'country', 'phone_number' => 'phonenumber', 'status' => 'status'] as $db => $api) {
            if ($request->has($api)) {
                $client->$db = $request->$api;
            }
        }
        $client->save();

        return $this->success(['clientid' => $client->id]);
    }

    public function deleteClient(Request $request)
    {
        $client = Client::find($request->clientid);
        if (! $client) {
            return $this->error('Client Not Found', 404);
        }

        // Same rule as the admin screen: terminate the services first, so the
        // accounts are actually closed on the server.
        $live = $client->liveServiceCount();
        if ($live > 0) {
            return $this->error("Client still has {$live} service(s) that have not been terminated.", 422);
        }

        $domains = $client->liveDomainCount();
        if ($domains > 0) {
            return $this->error("Client still has {$domains} registered domain(s).", 422);
        }

        $client->delete();

        return $this->success(['clientid' => $request->clientid]);
    }

    public function closeClient(Request $request)
    {
        $client = Client::find($request->clientid);
        if (! $client) {
            return $this->error('Client Not Found', 404);
        }
        $client->update(['status' => 'closed']);

        return $this->success(['clientid' => $client->id]);
    }

    public function addClientNote(Request $request)
    {
        $client = Client::find($request->clientid);
        if (! $client) {
            return $this->error('Client Not Found', 404);
        }
        ClientNote::create(['client_id' => $client->id, 'admin' => $request->adminusername ?? 'system', 'note' => $request->note ?? $request->notes ?? $request->message, 'sticky' => $request->boolean('sticky')]);

        return $this->success();
    }

    public function getContacts(Request $request)
    {
        $query = Contact::query();
        if ($request->filled('userid')) {
            $query->where('client_id', $request->userid);
        }

        return $this->paginated($query->paginate($this->getPerPage(), ['*'], 'page', $this->getPage()));
    }

    public function addContact(Request $request)
    {
        $validated = $request->validate(['clientid' => 'required|exists:clients,id', 'firstname' => 'required|string', 'lastname' => 'required|string', 'email' => 'required|email']);
        $contact = Contact::create(['client_id' => $validated['clientid'], 'first_name' => $validated['firstname'], 'last_name' => $validated['lastname'], 'email' => $validated['email'], 'phone_number' => $request->phonenumber]);

        return $this->success(['contactid' => $contact->id]);
    }

    public function updateContact(Request $request)
    {
        $contact = Contact::find($request->contactid);
        if (! $contact) {
            return $this->error('Contact Not Found', 404);
        }
        foreach (['first_name' => 'firstname', 'last_name' => 'lastname', 'email' => 'email'] as $db => $api) {
            if ($request->has($api)) {
                $contact->$db = $request->$api;
            }
        }
        $contact->save();

        return $this->success(['contactid' => $contact->id]);
    }

    public function deleteContact(Request $request)
    {
        $contact = Contact::find($request->contactid);
        if (! $contact) {
            return $this->error('Contact Not Found', 404);
        }
        $contact->delete();

        return $this->success();
    }

    public function getClientGroups()
    {
        return $this->success(['groups' => ClientGroup::all()->toArray()]);
    }

    public function getCredits(Request $request)
    {
        $query = Credit::query();
        if ($request->filled('clientid')) {
            $query->where('client_id', $request->clientid);
        }

        return $this->success(['credits' => $query->orderBy('id', 'desc')->get()->toArray()]);
    }

    public function addCredit(Request $request)
    {
        $validated = $request->validate(['clientid' => 'required|exists:clients,id', 'description' => 'required|string', 'amount' => 'required|numeric|min:0.01']);
        Credit::create(['client_id' => $validated['clientid'], 'date' => now()->format('Y-m-d'), 'description' => $validated['description'], 'amount' => $validated['amount']]);
        Client::find($validated['clientid'])->increment('credit', $validated['amount']);

        return $this->success();
    }

    public function getUsers(Request $request)
    {
        $query = User::query();
        if ($request->filled('clientid')) {
            $query->where('client_id', $request->clientid);
        }

        return $this->success(['users' => $query->paginate($this->getPerPage(), ['*'], 'page', $this->getPage())->items()]);
    }

    public function addUser(Request $request)
    {
        $validated = $request->validate(['email' => 'required|email', 'password' => 'required|min:6', 'first_name' => 'required', 'last_name' => 'required']);
        $validated['password'] = bcrypt($validated['password']);
        if ($request->filled('clientid')) {
            $validated['client_id'] = $request->clientid;
        }
        $user = User::create($validated);

        return $this->success(['userid' => $user->id]);
    }

    public function updateUser(Request $request)
    {
        $user = User::find($request->userid);
        if (! $user) {
            return $this->error('User Not Found', 404);
        }
        foreach (['email', 'first_name', 'last_name'] as $f) {
            if ($request->has($f)) {
                $user->$f = $request->$f;
            }
        }
        if ($request->filled('password')) {
            $user->password = bcrypt($request->password);
        }
        $user->save();

        return $this->success(['userid' => $user->id]);
    }

    public function deleteUserClient(Request $request)
    {
        $user = User::find($request->userid);
        if (! $user) {
            return $this->error('User Not Found', 404);
        }
        $user->delete();

        return $this->success();
    }

    public function createClientInvite(Request $request)
    {
        // An invite code that is stored nowhere can never be redeemed.
        return $this->error('Client invites are not implemented.', 501);
    }

    public function getUserPermissions(Request $request)
    {
        // The list was the same whoever was asked about, because nothing stores
        // per-user permissions: every login on an account has the same access.
        return $this->error('Per-user permissions are not implemented; every login on an account has the same access.', 501);
    }

    public function updateUserPermissions(Request $request)
    {
        // Reporting that access had been restricted while storing nothing is
        // the worst answer of the three available.
        return $this->error('Per-user permissions are not implemented.', 501);
    }

    public function getClientPassword(Request $request)
    {
        return $this->error('Password retrieval not supported for security reasons', 403);
    }

    public function createSSOToken(Request $request)
    {
        // The token was random characters kept nowhere, so the link it came
        // with could never have signed anyone in.
        return $this->error('Single sign-on tokens are not implemented.', 501);
    }
}
