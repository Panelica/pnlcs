<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\CsvExportable;
use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\ClientNote;
use App\Models\Currency;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    use CsvExportable;

    public function index(Request $request)
    {
        $query = Client::with('contacts');
        if ($request->filled('search')) { $query->search($request->search); }
        if ($request->filled('status')) { $query->where('status', $request->status); }
        if ($request->filled('group_id')) { $query->where('group_id', $request->group_id); }
        $clients = $query->orderBy('created_at', 'desc')->paginate(25);
        $groups = ClientGroup::all();
        return view('admin.clients.index', compact('clients', 'groups'));
    }

    public function create()
    {
        $groups = ClientGroup::all();
        $currencies = Currency::all();
        return view('admin.clients.create', compact('groups', 'currencies'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:clients,email',
            'company_name' => 'nullable|string|max:255',
            'address1' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:2',
            'phone_number' => 'nullable|string|max:30',
            'status' => 'required|in:active,inactive,closed',
            'group_id' => 'nullable|exists:client_groups,id',
            'currency_id' => 'nullable|exists:currencies,id',
        ]);
        $client = Client::create($validated);
        return redirect()->route('admin.clients.show', $client)->with('success', __('messages.success.client_created'));
    }

    public function show(Request $request, Client $client)
    {
        $tab = $request->get('tab', 'summary');

        $client->load('contacts', 'users');

        $data = ['client' => $client, 'tab' => $tab];

        // Stats always needed (shown in all tabs)
        $data['serviceCount'] = $client->services()->count();
        $data['domainCount'] = $client->domains()->count();
        $data['invoiceCount'] = $client->invoices()->count();
        $data['ticketCount'] = $client->tickets()->count();
        $data['unpaidInvoices'] = $client->invoices()->where('status', 'unpaid')->sum('total');

        switch ($tab) {
            case 'services':
                $data['services'] = $client->services()->with('product')->orderBy('id', 'desc')->paginate(15);
                break;
            case 'domains':
                $data['domains'] = $client->domains()->orderBy('id', 'desc')->paginate(15);
                break;
            case 'invoices':
                $data['invoices'] = $client->invoices()->orderBy('id', 'desc')->paginate(15);
                break;
            case 'tickets':
                $data['tickets'] = $client->tickets()->with('department')->orderBy('id', 'desc')->paginate(15);
                break;
            case 'notes':
                $data['notes'] = ClientNote::where('client_id', $client->id)->orderBy('id', 'desc')->get();
                break;
            case 'log':
                $data['logs'] = \App\Models\ActivityLog::where('description', 'LIKE', '%client #' . $client->id . '%')
                    ->orWhere('description', 'LIKE', '%' . $client->email . '%')
                    ->orderBy('id', 'desc')->paginate(25);
                break;
            default: // summary
                $data['serviceCount'] = $client->services()->count();
                $data['domainCount'] = $client->domains()->count();
                $data['invoiceCount'] = $client->invoices()->count();
                $data['ticketCount'] = $client->tickets()->count();
                $data['unpaidInvoices'] = $client->invoices()->where('status', 'unpaid')->sum('total');
                $data['recentInvoices'] = $client->invoices()->orderBy('id', 'desc')->limit(5)->get();
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

        ClientNote::create([
            'client_id' => $client->id,
            'admin_id' => auth('admin')->id(),
            'note' => $validated['note'],
            'sticky' => $validated['sticky'] ?? false,
        ]);

        return back()->with('success', __('messages.success.note_added'));
    }

    public function edit(Client $client)
    {
        $groups = ClientGroup::all();
        $currencies = Currency::all();
        return view('admin.clients.edit', compact('client', 'groups', 'currencies'));
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:clients,email',
            'company_name' => 'nullable|string|max:255',
            'address1' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:2',
            'phone_number' => 'nullable|string|max:30',
            'status' => 'required|in:active,inactive,closed',
            'group_id' => 'nullable|exists:client_groups,id',
            'currency_id' => 'nullable|exists:currencies,id',
        ]);
        $client->update($validated);
        return redirect()->route('admin.clients.show', $client)->with('success', __('messages.success.client_updated'));
    }

    public function destroy(Client $client)
    {
        $client->delete();
        return redirect()->route('admin.clients.index')->with('success', __('messages.success.client_deleted'));
    }

    /**
     * Export clients list as CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $query = Client::query();
        if ($request->filled('search')) { $query->search($request->search); }
        if ($request->filled('status')) { $query->where('status', $request->status); }
        if ($request->filled('group_id')) { $query->where('group_id', $request->group_id); }

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
            'clients-' . now()->format('Y-m-d') . '.csv',
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
        if (!$user) {
            return back()->with('error', __('messages.error.no_user_linked'));
        }

        // Login as the client's user
        auth()->login($user);

        return redirect()->route('client.home')->with('success', __('admin.messages.viewing_as', ['name' => $client->first_name . ' ' . $client->last_name]));
    }

    /**
     * Stop impersonation and return to admin.
     */
    public function stopImpersonation()
    {
        $adminId = session('impersonating_admin_id');
        if (!$adminId) {
            return redirect()->route('admin.dashboard');
        }

        // Logout client
        auth()->logout();

        // Login back as admin
        $admin = \App\Models\Admin::find($adminId);
        if ($admin) {
            auth('admin')->login($admin);
        }

        // Clear impersonation session
        session()->forget(['impersonating_admin_id', 'impersonating_admin_name']);

        return redirect()->route('admin.clients.index')->with('success', __('messages.success.impersonation_stopped'));
    }
}
