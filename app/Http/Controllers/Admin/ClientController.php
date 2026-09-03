<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\CsvExportable;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\ClientNote;
use App\Models\Currency;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Enums\ServiceStatus;
use App\Services\Module\ModuleRegistry;
use App\Services\ProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    use CsvExportable;

    public function index(Request $request)
    {
        $query = Client::with('contacts');
        if ($request->filled('search')) {
            $query->search($request->search);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('group_id')) {
            $query->where('group_id', $request->group_id);
        }
        $clients = $query->orderBy('created_at', 'desc')->paginate(25);
        $groups = ClientGroup::all();

        return view('admin.clients.index', compact('clients', 'groups'));
    }

    public function create()
    {
        $groups = ClientGroup::all();
        $currencies = Currency::all();
        $customFields = CustomField::clientFields()->get();
        $paymentMethods = $this->paymentMethods();
        $defaultPaymentMethod = Setting::get('DefaultPaymentMethod', 'banktransfer');

        return view('admin.clients.create', compact('groups', 'currencies', 'customFields', 'paymentMethods', 'defaultPaymentMethod'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:clients,email|unique:users,email',
            'billing_email' => 'nullable|email|max:255',
            'company_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:20',
            'address1' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:2',
            'phone_number' => 'nullable|string|max:30',
            'phone_prefix' => 'nullable|string|max:4',
            'status' => 'required|in:active,inactive,closed',
            'group_id' => 'nullable|exists:client_groups,id',
            'currency_id' => 'nullable|exists:currencies,id',
            'default_payment_method' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:8|confirmed',
        ]);
        $client = Client::create($validated);

        // A client created by staff gets a login account too, otherwise there
        // is no user to sign the client area in as (or to impersonate with).
        if (! empty($validated['password'])) {
            $user = User::create([
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'email' => $client->email,
                'password' => Hash::make($validated['password']),
                'is_active' => true,
            ]);
            $client->users()->attach($user->id, ['owner' => true]);
        }

        $this->saveCustomFieldValues($client, $request);

        return redirect()->route('admin.clients.show', $client)->with('success', __('messages.success.client_created'));
    }

    public function show(Request $request, Client $client)
    {
        $tab = $request->get('tab', 'summary');

        $client->load('contacts', 'users');

        $data = ['client' => $client, 'tab' => $tab];

        // Custom fields (shown in the summary tab)
        $data['customFields'] = CustomField::clientFields()
            ->with(['values' => fn ($q) => $q->where('rel_id', $client->id)])
            ->get();

        // Stats always needed (shown in all tabs)
        $data['serviceCount'] = $client->services()->count();
        $data['domainCount'] = $client->domains()->count();
        $data['invoiceCount'] = $client->invoices()->excludeSettledProformas()->count();
        $data['ticketCount'] = $client->tickets()->count();
        $data['unpaidInvoices'] = $client->invoices()->where('status', 'unpaid')->sum('total');

        switch ($tab) {
            case 'services':
                $data['services'] = $client->services()->with('product')->orderBy('id', 'desc')->paginate(15);
                // For the "add existing service" form (used when migrating a
                // customer in by hand): only real, sellable products and the
                // servers an operator can place them on.
                $data['products'] = Product::where('retired', false)->orderBy('name')->get(['id', 'name', 'server_type']);
                $data['servers'] = Server::where('active', true)->orderBy('name')->get(['id', 'name', 'type']);
                break;
            case 'domains':
                $data['domains'] = $client->domains()->orderBy('id', 'desc')->paginate(15);
                break;
            case 'invoices':
                $data['invoices'] = $client->invoices()->excludeSettledProformas()->orderBy('id', 'desc')->paginate(15);
                break;
            case 'tickets':
                $data['tickets'] = $client->tickets()->with('department')->orderBy('id', 'desc')->paginate(15);
                break;
            case 'notes':
                $data['notes'] = ClientNote::where('client_id', $client->id)->orderBy('id', 'desc')->get();
                break;
            case 'log':
                $data['logs'] = ActivityLog::forClient($client)
                    ->orderBy('id', 'desc')->paginate(25);
                break;
            default: // summary
                $data['serviceCount'] = $client->services()->count();
                $data['domainCount'] = $client->domains()->count();
                $data['invoiceCount'] = $client->invoices()->excludeSettledProformas()->count();
                $data['ticketCount'] = $client->tickets()->count();
                $data['unpaidInvoices'] = $client->invoices()->where('status', 'unpaid')->sum('total');
                $data['recentInvoices'] = $client->invoices()->excludeSettledProformas()->orderBy('id', 'desc')->limit(5)->get();
                $data['recentTickets'] = $client->tickets()->with('department')->orderBy('id', 'desc')->limit(5)->get();
                $data['recentServices'] = $client->services()->with('product')->orderBy('id', 'desc')->limit(5)->get();
                break;
        }

        return view('admin.clients.show', $data);
    }

    public function storeNote(Request $request, Client $client)
    {
        $validated = $request->validate([
            'note' => 'required|string',
            'sticky' => 'boolean',
        ]);

        // client_notes keeps its author as a plain name in `admin`; there is no
        // admin_id column and the model does not accept one, so writing an id
        // here left every note attributed to nobody in particular. The API door
        // has always written the name.
        ClientNote::create([
            'client_id' => $client->id,
            'admin' => auth('admin')->user()?->full_name ?: 'system',
            'note' => $validated['note'],
            'sticky' => $validated['sticky'] ?? false,
        ]);

        return back()->with('success', __('messages.success.note_added'));
    }

    /**
     * Attach an existing service to a client by hand.
     *
     * This is the migration path the docs describe: a customer already runs on
     * a server (from cPanel/WHMCS/manual setup) and the operator records that
     * service in PNLCS so renewals bill from here. It deliberately does NOT
     * provision anything on the panel — the account already exists — it only
     * writes the billing record, so there is no order and no module call.
     */
    public function storeService(Request $request, Client $client, ProvisioningService $provisioning)
    {
        $cycles = ['Monthly', 'Quarterly', 'Semi-Annually', 'Annually', 'Biennially', 'Triennially', 'One-Time'];

        $validated = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')],
            // A server is required when we are going to provision on it.
            'server_id' => [Rule::requiredIf($request->boolean('provision')), 'nullable', Rule::exists('servers', 'id')],
            'domain' => ['nullable', 'string', 'max:255'],
            'billing_cycle' => ['required', Rule::in($cycles)],
            'amount' => ['required', 'numeric', 'min:0'],
            'next_due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_column(ServiceStatus::cases(), 'value'))],
            'provision' => ['boolean'],
        ]);

        // Two modes, the way WHMCS lets you choose a module command on a manual
        // add: provision on, and we run the server module's Create to build a
        // real account; provision off, and we only write the billing record for
        // a service that already exists (migration). Provisioning starts the
        // service pending; createAccount() activates it when the module says ok.
        $provision = $request->boolean('provision');

        $service = Service::create([
            'client_id' => $client->id,
            'product_id' => $validated['product_id'],
            'server_id' => $validated['server_id'] ?? null,
            'domain' => $validated['domain'] ?? null,
            'qty' => 1,
            'first_payment_amount' => $validated['amount'],
            'amount' => $validated['amount'],
            'billing_cycle' => $validated['billing_cycle'],
            'next_due_date' => $validated['next_due_date'] ?? null,
            'registration_date' => now()->toDateString(),
            'status' => $provision ? ServiceStatus::Pending->value : $validated['status'],
            'auto_renew' => true,
        ]);

        ActivityLog::log(
            $provision
                ? "Service #{$service->id} added to client #{$client->id} and provisioned on the server"
                : "Existing service #{$service->id} added to client #{$client->id} (manual migration)",
            auth('admin')->user()?->full_name ?: 'admin',
            $client->id,
        );

        $redirect = redirect()->route('admin.clients.show', ['client' => $client, 'tab' => 'services']);

        if ($provision) {
            $result = $provisioning->createAccount($service);

            return ($result['success'] ?? false)
                ? $redirect->with('success', __('admin.clients.service_provisioned'))
                : $redirect->with('error', __('admin.clients.service_provision_failed', ['error' => $result['message'] ?? '']));
        }

        return $redirect->with('success', __('admin.clients.service_added'));
    }

    public function edit(Client $client)
    {
        $groups = ClientGroup::all();
        $currencies = Currency::all();
        $customFields = CustomField::clientFields()->with(['values' => fn ($q) => $q->where('rel_id', $client->id)])->get();
        $paymentMethods = $this->paymentMethods();

        return view('admin.clients.edit', compact('client', 'groups', 'currencies', 'customFields', 'paymentMethods'));
    }

    /**
     * Payment methods the client's default can be picked from: every usable
     * gateway plus the offline options offered on the invoice form.
     *
     * @return array<int, string>
     */
    protected function paymentMethods(): array
    {
        $gateways = app(ModuleRegistry::class)->usableGateways();

        if (! in_array('banktransfer', $gateways, true)) {
            $gateways[] = 'banktransfer';
        }

        $gateways[] = 'manual';

        return $gateways;
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            // Excluding the row being edited: without it a client's own
            // address counted as taken and no change could be saved at all.
            'email' => ['required', 'email', 'max:255', Rule::unique('clients', 'email')->ignore($client->id)],
            'billing_email' => 'nullable|email|max:255',
            'company_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:20',
            'address1' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:20',
            // The column will not hold a null, so asking beats a 500.
            'country' => 'required|string|size:2',
            'phone_number' => 'nullable|string|max:30',
            'phone_prefix' => 'nullable|string|max:4',
            'status' => 'required|in:active,inactive,closed',
            'group_id' => 'nullable|exists:client_groups,id',
            'currency_id' => 'nullable|exists:currencies,id',
            'default_payment_method' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        // An optional new login password (blank leaves it unchanged).
        if (! empty($validated['password'])) {
            $this->setClientPassword($client, $validated['password']);
        }

        unset($validated['password'], $validated['password_confirmation']);

        $client->update($validated);
        $this->saveCustomFieldValues($client, $request);

        return redirect()->route('admin.clients.show', $client)->with('success', __('messages.success.client_updated'));
    }

    /**
     * Set (or create) the linked login user's password for a client.
     */
    protected function setClientPassword(Client $client, string $password): void
    {
        $user = $client->users()->wherePivot('owner', true)->first()
            ?? $client->users()->first();

        if (! $user) {
            $user = User::create([
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'email' => $client->email,
                'password' => Hash::make($password),
                'is_active' => true,
            ]);

            $client->users()->attach($user->id, ['owner' => true]);

            return;
        }

        $user->password = Hash::make($password);
        $user->save();
    }

    public function destroy(Client $client)
    {
        // Deleting a client cascades their services away, but the accounts
        // themselves are never terminated on the control panel - the hosting
        // would carry on running with nothing left to say it exists or who it
        // belongs to. Terminate first, then delete.
        $live = $client->liveServiceCount();

        if ($live > 0) {
            return back()->with('error', __('admin.messages.client_has_live_services', ['count' => $live]));
        }

        // A registration outlives the panel record: delete the customer and the
        // domain row goes with them, so nothing renews it and nothing says it
        // is theirs, while it stays registered until it quietly lapses.
        $domains = $client->liveDomainCount();

        if ($domains > 0) {
            return back()->with('error', __('admin.messages.client_has_live_domains', ['count' => $domains]));
        }

        $client->delete();

        return redirect()->route('admin.clients.index')->with('success', __('messages.success.client_deleted'));
    }

    /**
     * Export clients list as CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
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

        $clients = $query->orderBy('id', 'asc')->get([
            'id', 'first_name', 'last_name', 'email', 'company_name',
            'status', 'country', 'phone_number', 'credit', 'created_at',
        ]);

        $rows = $clients->map(fn ($c) => [
            $c->id,
            $c->first_name,
            $c->last_name,
            $c->email,
            $c->company_name ?? '',
            $c->status->value ?? $c->status,
            $c->country ?? '',
            $c->phone_number ?? '',
            $c->credit ?? '0.00',
            $c->created_at?->format('Y-m-d H:i:s') ?? '',
        ]);

        return $this->streamCsvDownload(
            'clients-'.now()->format('Y-m-d').'.csv',
            ['ID', 'First Name', 'Last Name', 'Email', 'Company', 'Status', 'Country', 'Phone', 'Credit', 'Created At'],
            $rows
        );
    }

    /**
     * Login as a client (impersonation).
     */
    public function impersonate(Client $client)
    {
        // Store admin session info
        session(['impersonating_admin_id' => auth('admin')->id()]);
        session(['impersonating_admin_name' => auth('admin')->user()->username]);

        // Find the user associated with this client
        $user = $client->users()->first();
        if (! $user) {
            return back()->with('error', __('messages.error.no_user_linked'));
        }

        // Login as the client's user
        auth()->login($user);

        // The customer's second factor must not stand in the way of an admin
        // taking the account over: the admin has already authenticated as an
        // admin, carries the edit_clients permission, and cannot hold the
        // customer's TOTP. Without this the client area's 2fa middleware
        // bounced the admin to the customer's 2FA screen with no way through,
        // so a 2FA-enabled client could not be impersonated at all.
        session(['2fa_verified' => true]);

        // The user may belong to more than one account: open the one that was
        // clicked, not whichever happens to come first.
        session(['active_client_id' => $client->id]);

        // r141-trail: leave a record of who was in the account.
        //
        // Nine ordinary events are written to the activity log with the
        // customer's id on them and the customer's own Log tab reads them
        // back; taking the account over wrote nothing at all. Staff could sign
        // in as the customer, place an order, open a ticket, change the
        // account, and there was no record that anyone had been there - so a
        // customer saying "I never ordered that" could not be answered.
        ActivityLog::log(
            "Admin signed in as this client (account #{$client->id})",
            session('impersonating_admin_name'),
            $client->id
        );

        return redirect()->route('client.home')->with('success', __('admin.messages.viewing_as', ['name' => $client->first_name.' '.$client->last_name]));
    }

    /**
     * Stop impersonation and return to admin.
     */
    public function stopImpersonation()
    {
        $adminId = session('impersonating_admin_id');
        if (! $adminId) {
            return redirect()->route('admin.dashboard');
        }

        // Logout client
        auth()->logout();

        // Login back as admin
        $admin = Admin::find($adminId);
        if ($admin) {
            auth('admin')->login($admin);
        }

        // The other half of the record: when they stopped.
        if ($clientId = session('active_client_id')) {
            ActivityLog::log(
                "Admin stopped signing in as this client (account #{$clientId})",
                session('impersonating_admin_name'),
                (int) $clientId
            );
        }

        // Clear impersonation session, including the 2FA pass impersonation
        // granted itself: leaving it set would let the next real client login
        // in this browser skip its own second factor.
        session()->forget(['impersonating_admin_id', 'impersonating_admin_name', 'active_client_id', '2fa_verified']);

        return redirect()->route('admin.clients.index')->with('success', __('messages.success.impersonation_stopped'));
    }

    /**
     * Persist the submitted custom field values for a client.
     */
    protected function saveCustomFieldValues(Client $client, Request $request)
    {
        $fields = CustomField::clientFields()->get()->keyBy('id');

        foreach ($fields as $id => $field) {
            $raw = $request->input("custom_fields.$id");

            $value = is_array($raw) ? implode(', ', array_filter((array) $raw)) : (string) $raw;

            if ($value === '') {
                CustomFieldValue::where('field_id', $id)->where('rel_id', $client->id)->delete();

                continue;
            }

            CustomFieldValue::updateOrCreate(
                ['field_id' => $id, 'rel_id' => $client->id],
                ['value' => $value]
            );
        }
    }
}
