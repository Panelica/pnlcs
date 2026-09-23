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
use Illuminate\Support\Facades\DB;

class ClientApiController extends BaseApiController
{
    /** The columns a caller may order the client list by. */
    private const ORDERABLE = ['id', 'first_name', 'last_name', 'email', 'company_name', 'status', 'created_at'];

    public function getClients(Request $request)
    {
        // WHMCS orders the list with "sorting" (ASC/DESC).
        $this->alias($request, 'sorting', 'order');
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
        // The docs (and WHMCS) have always promised "clientid or email"; only
        // clientid was ever read, so integrators following the docs got
        // "Client Not Found" for perfectly good requests.
        if (! $request->filled('clientid') && ! $request->filled('email')) {
            return $this->error('Client ID or Email Required', 400);
        }

        $client = Client::with('contacts')
            ->when($request->filled('clientid'),
                fn ($q) => $q->whereKey($request->clientid),
                fn ($q) => $q->where('email', (string) $request->email))
            ->first();
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

        // The docs (and WHMCS) promise that a password2 opens a portal login.
        // This method used to swallow the parameter silently, leaving an
        // account nobody could sign in to. Portal logins live on User, so the
        // registration service does it - one copy, or the two paths drift.
        $password = $request->input('password2', $request->input('password'));
        if ($password !== null && $password !== '') {
            $request->validate([
                'password2' => 'sometimes|string|min:8',
                'password' => 'sometimes|string|min:8',
                'email' => 'unique:users,email',
            ]);
            [, $client] = app(\App\Services\ClientRegistrationService::class)->register([
                'first_name' => $validated['firstname'],
                'last_name' => $validated['lastname'],
                'email' => $validated['email'],
                'password' => $password,
                'company_name' => $request->companyname,
                'address1' => $request->address1,
                'city' => $request->city,
                'country' => $request->country ?? 'US',
                'phone_number' => $request->phonenumber,
            ], $request);
            // The service carries the fields the register form has; the API
            // has always accepted these two on top.
            $client->update(['state' => $request->state, 'postcode' => $request->postcode]);

            return $this->success(['clientid' => $client->id]);
        }

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
            // Sent empty, a name reached a NOT NULL column as a 500.
            'firstname' => 'sometimes|required|string|max:255',
            'lastname' => 'sometimes|required|string|max:255',
            'country' => 'sometimes|nullable|string|size:2',
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
        $this->alias($request, 'userid', 'clientid');
        $client = Client::find($request->clientid);
        if (! $client) {
            return $this->error('Client Not Found', 404);
        }
        // Signed by the caller. The author was whatever "adminusername" said,
        // so a note could be put on a customer's record in any colleague's
        // name; and an empty note reached a NOT NULL column as a 500.
        $note = $request->note ?? $request->notes ?? $request->message;
        if (! is_string($note) || trim($note) === '') {
            return $this->error('A note is required.', 422);
        }
        ClientNote::create(['client_id' => $client->id, 'admin' => (string) (auth('admin')->user()?->username ?? 'API'), 'note' => $note, 'sticky' => $request->boolean('sticky')]);

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
        $request->validate([
            'firstname' => 'sometimes|required|string|max:255',
            'lastname' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255',
        ]);
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
        // The ledger line and the balance move together or not at all.
        DB::transaction(function () use ($validated) {
            Credit::create(['client_id' => $validated['clientid'], 'date' => now()->format('Y-m-d'), 'description' => $validated['description'], 'amount' => $validated['amount']]);
            Client::whereKey($validated['clientid'])->increment('credit', $validated['amount']);
        });

        return $this->success();
    }

    public function getUsers(Request $request)
    {
        $query = User::query();
        // Logins belong to accounts through the client_user pivot; users carry
        // no client_id column, and filtering on one answered with a database
        // error.
        if ($request->filled('clientid')) {
            $query->whereHas('clients', fn ($q) => $q->whereKey($request->clientid));
        }

        return $this->success(['users' => $query->paginate($this->getPerPage(), ['*'], 'page', $this->getPage())->items()]);
    }

    public function addUser(Request $request)
    {
        // users.email is unique in the database; checking it here turns a
        // taken address into a 422 instead of a 500. Eight characters is what
        // addclient and the registration form ask for.
        $validated = $request->validate(['email' => 'required|email|max:255|unique:users,email', 'password' => 'required|string|min:8', 'first_name' => 'required|string|max:255', 'last_name' => 'required|string|max:255', 'clientid' => 'nullable|integer|exists:clients,id']);
        unset($validated['clientid']);
        $validated['password'] = bcrypt($validated['password']);
        $user = User::create($validated);
        // Attach through the pivot: a client_id written on the user went
        // nowhere, and the login could open no account.
        if ($request->filled('clientid') && Client::whereKey($request->clientid)->exists()) {
            $user->clients()->syncWithoutDetaching([(int) $request->clientid]);
        }

        return $this->success(['userid' => $user->id]);
    }

    public function updateUser(Request $request)
    {
        $user = User::find($request->userid);
        if (! $user) {
            return $this->error('User Not Found', 404);
        }
        $request->validate([
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'password' => 'sometimes|nullable|string|min:8',
        ]);
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

    /**
     * Take a login off one account (WHMCS DeleteUserClient).
     *
     * This deleted the login outright. A login can open several accounts, so
     * asking to remove it from one closed every other account it belonged to
     * as well, and there is no undo. It now does what the name says: the link
     * between this login and this account goes, the login stays.
     */
    public function deleteUserClient(Request $request)
    {
        $request->validate(['userid' => 'required|integer', 'clientid' => 'required|integer']);

        $user = User::find($request->userid);
        if (! $user) {
            return $this->error('User Not Found', 404);
        }
        if (! $user->clients()->whereKey($request->clientid)->exists()) {
            return $this->error('That login is not on that account.', 404);
        }
        $user->clients()->detach((int) $request->clientid);

        return $this->success(['userid' => $user->id, 'clientid' => (int) $request->clientid]);
    }

    /**
     * Invite somebody to an account (WHMCS CreateClientInvite). They get an
     * email with a link, good for UserInvite::VALID_DAYS days; opening it they
     * make a login, or sign in with theirs, and join the account with the
     * permissions given here ("all", or a list of ClientPermissions::ALL).
     */
    public function createClientInvite(Request $request)
    {
        $this->alias($request, 'client_id', 'clientid');
        $validated = $request->validate([
            'clientid' => 'required|integer|exists:clients,id',
            'email' => 'required|email|max:255',
            'permissions' => 'nullable',
        ]);

        $permissions = \App\Support\ClientPermissions::parse($request->input('permissions'));
        if ($permissions === null) {
            return $this->error('Unknown permission. Use "all" or any of: '.implode(', ', \App\Support\ClientPermissions::ALL).'.', 422);
        }

        $client = Client::findOrFail($validated['clientid']);
        $email = strtolower($validated['email']);

        if ($client->users()->where('email', $email)->exists()) {
            return $this->error('That address already has a login on this account.', 409);
        }

        $plain = \Illuminate\Support\Str::random(64);
        $invite = \App\Models\UserInvite::create([
            'token' => hash('sha256', $plain),
            'email' => $email,
            'client_id' => $client->id,
            'invited_by' => auth('admin')->id() ?? 0,
            'permissions' => $permissions,
        ]);

        $link = route('client.invite.show', $plain);
        $locale = $client->language ?: config('app.locale');
        $account = trim($client->company_name ?: $client->first_name.' '.$client->last_name);
        \Illuminate\Support\Facades\Mail::to($email)->queue(new \App\Mail\BulkMassMail(
            __('client.invite.mail_subject', ['company' => company_name()], $locale),
            __('client.invite.mail_body', ['account' => $account, 'company' => company_name(), 'link' => $link, 'days' => \App\Models\UserInvite::VALID_DAYS], $locale),
            $email
        ));

        return $this->success(['inviteid' => $invite->id, 'email' => $email, 'permissions' => $permissions]);
    }

    /** What a login may do on an account (WHMCS GetUserPermissions). */
    public function getUserPermissions(Request $request)
    {
        $this->alias($request, 'user_id', 'userid');
        $this->alias($request, 'client_id', 'clientid');
        $request->validate(['userid' => 'required|integer', 'clientid' => 'required|integer']);

        $user = User::find($request->userid);
        $client = Client::find($request->clientid);
        $granted = ($user && $client) ? \App\Support\ClientPermissions::granted($user, $client) : null;
        if ($granted === null) {
            return $this->error('That login is not on that account.', 404);
        }

        $owner = (bool) $user->clients()->whereKey($client->id)->first()->pivot->owner;

        return $this->success(['userid' => $user->id, 'clientid' => $client->id, 'owner' => $owner, 'permissions' => $granted]);
    }

    /**
     * Restrict or widen what a login may do on an account (WHMCS
     * UpdateUserPermissions). The owner always has everything.
     */
    public function updateUserPermissions(Request $request)
    {
        $this->alias($request, 'user_id', 'userid');
        $this->alias($request, 'client_id', 'clientid');
        $request->validate(['userid' => 'required|integer', 'clientid' => 'required|integer', 'permissions' => 'required']);

        $user = User::find($request->userid);
        $pivot = $user?->clients()->whereKey($request->clientid)->first()?->pivot;
        if (! $pivot) {
            return $this->error('That login is not on that account.', 404);
        }
        if ($pivot->owner) {
            return $this->error('The account owner always has every permission.', 422);
        }

        $permissions = \App\Support\ClientPermissions::parse($request->input('permissions'));
        if ($permissions === null) {
            return $this->error('Unknown permission. Use "all" or any of: '.implode(', ', \App\Support\ClientPermissions::ALL).'.', 422);
        }

        $user->clients()->updateExistingPivot((int) $request->clientid, ['permissions' => json_encode($permissions)]);

        return $this->success(['userid' => $user->id, 'clientid' => (int) $request->clientid, 'permissions' => $permissions]);
    }

    public function getClientPassword(Request $request)
    {
        return $this->error('Password retrieval not supported for security reasons', 403);
    }

    /**
     * A one-time link that signs a login into the client area (WHMCS
     * CreateSsoToken). Good for 60 seconds and one use; only its hash is kept.
     * Needs the same permission as "log in as this client" in the admin area.
     *
     * destination: clientarea:homepage (default), clientarea:invoices,
     * clientarea:services, clientarea:domains, clientarea:tickets,
     * clientarea:product_details (service_id), clientarea:domain_details
     * (domain_id), or sso:custom_redirect with sso_redirect_path (a path
     * inside the client area).
     */
    public function createSSOToken(Request $request)
    {
        $this->alias($request, 'client_id', 'clientid');
        $this->alias($request, 'user_id', 'userid');
        $request->validate([
            'clientid' => 'required|integer|exists:clients,id',
            'userid' => 'nullable|integer',
            'destination' => 'nullable|string|max:100',
            'service_id' => 'nullable|integer',
            'domain_id' => 'nullable|integer',
            'sso_redirect_path' => 'nullable|string|max:255',
        ]);

        $client = Client::findOrFail($request->clientid);
        $user = $request->filled('userid')
            ? $client->users()->whereKey($request->userid)->first()
            : ($client->owner() ?? $client->users()->first());
        if (! $user) {
            return $this->error('No login on that account to sign in as.', 404);
        }

        $path = match ($request->input('destination', 'clientarea:homepage')) {
            'clientarea:homepage' => route('client.home', [], false),
            'clientarea:invoices' => route('client.invoices.index', [], false),
            'clientarea:services' => route('client.services.index', [], false),
            'clientarea:domains' => route('client.domains.index', [], false),
            'clientarea:tickets' => route('client.tickets.index', [], false),
            'clientarea:product_details' => ($s = $client->services()->find($request->service_id)) ? route('client.services.show', $s, false) : null,
            'clientarea:domain_details' => ($d = $client->domains()->find($request->domain_id)) ? route('client.domains.show', $d, false) : null,
            'sso:custom_redirect' => self::clientAreaPath((string) $request->sso_redirect_path),
            default => false,
        };
        if ($path === false) {
            return $this->error('Unknown destination.', 422);
        }
        if ($path === null) {
            return $this->error('The destination is not on this account, or not a path inside the client area.', 422);
        }

        $plain = \Illuminate\Support\Str::random(64);
        \App\Models\ClientSsoToken::create([
            'token_hash' => hash('sha256', $plain),
            'user_id' => $user->id,
            'client_id' => $client->id,
            'admin_id' => auth('admin')->id(),
            'redirect_path' => $path,
            'expires_at' => now()->addSeconds(60),
        ]);

        return $this->success([
            'access_token' => $plain,
            'redirect_url' => route('client.sso', $plain),
            'expires_in' => 60,
        ]);
    }

    /** A path inside the client area - never another host, never a scheme. */
    private static function clientAreaPath(string $path): ?string
    {
        $path = '/'.ltrim(trim($path), '/');

        return preg_match('#^/client(/[A-Za-z0-9_\-./?=&%]*)?$#', $path) && ! str_contains($path, '..') && ! str_contains($path, '//')
            ? $path
            : null;
    }
}
