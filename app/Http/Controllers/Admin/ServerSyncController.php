<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Server;
use App\Services\ServerAccountSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Server sync: every account on a server next to the billing service that
 * manages it, and the ones without one imported in a batch.
 */
class ServerSyncController extends Controller
{
    public function __construct(private ServerAccountSync $sync) {}

    public function show(Request $request, Server $server): View
    {
        $module = $this->sync->moduleFor($server);
        abort_unless($module !== null, 404);

        $error = null;
        try {
            $rows = $this->sync->rows($server, $module, (array) $request->session()->get($this->priceListKey($server), []));
        } catch (\Throwable $e) {
            $rows = [];
            $error = $e->getMessage();
        }

        return view('admin.config.server-sync', [
            'server' => $server,
            'rows' => $rows,
            'error' => $error,
            'products' => $this->sync->products($server),
            'clients' => Client::orderBy('company_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'company_name', 'email']),
            'cycles' => ServerAccountSync::CYCLES,
            'priceListColumns' => ServerAccountSync::PRICE_LIST_COLUMNS,
            'priceListCount' => count((array) $request->session()->get($this->priceListKey($server), [])),
            'canImport' => $this->mayImport(),
        ]);
    }

    /**
     * Keeps an uploaded price list for this server for the session, so the
     * rows it names open with their client, price, cycle and renewal filled.
     */
    public function priceList(Request $request, Server $server): RedirectResponse
    {
        abort_unless($this->sync->moduleFor($server) !== null, 404);

        if ($request->boolean('clear')) {
            $request->session()->forget($this->priceListKey($server));

            return redirect()->route('admin.config.servers.sync', $server);
        }

        $request->validate(['price_list' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $rows = $this->sync->readPriceList($request->file('price_list')->getRealPath());
        $request->session()->put($this->priceListKey($server), $rows);

        return redirect()->route('admin.config.servers.sync', $server)
            ->with('success', __('admin.server_sync.price_list_loaded', ['count' => count($rows)]));
    }

    public function import(Request $request, Server $server): RedirectResponse
    {
        $module = $this->sync->moduleFor($server);
        abort_unless($module !== null, 404);
        abort_unless($this->mayImport(), 403);

        $productIds = $this->sync->products($server)->pluck('id')->all();
        $validated = $request->validate([
            'rows' => ['required', 'array', 'max:100'],
            'rows.*.client_id' => ['required', 'integer', Rule::exists('clients', 'id')],
            'rows.*.product_id' => ['required', 'integer', Rule::in($productIds)],
            'rows.*.billing_cycle' => ['required', Rule::in(ServerAccountSync::CYCLES)],
            'rows.*.amount' => ['required', 'numeric', 'min:0'],
            'rows.*.next_due_date' => ['nullable', 'date'],
        ], [], ['rows' => __('admin.server_sync.selection')]);

        // The accounts are read again, so only what is on the server now is imported.
        $accounts = collect($this->sync->accounts($server, $module))->keyBy('id');
        set_time_limit(300);

        $imported = 0;
        $errors = [];
        $by = auth('admin')->user()?->full_name ?: 'admin';
        foreach ($validated['rows'] as $accountId => $billing) {
            $account = $accounts->get((string) $accountId);
            if (! $account) {
                $errors[] = __('admin.server_sync.account_gone', ['account' => $accountId]);

                continue;
            }

            $result = $this->sync->import($server, $module, $account, $billing, $by);
            $result['success'] ? $imported++ : $errors[] = $result['message'];
        }

        $redirect = redirect()->route('admin.config.servers.sync', $server);
        if ($imported > 0) {
            $redirect->with('success', __('admin.server_sync.imported', ['count' => $imported]));
        }

        return $errors === [] ? $redirect : $redirect->with('error', implode(' · ', $errors));
    }

    /**
     * Importing writes client services and takes over server accounts: the
     * permissions adding and linking a single service ask for.
     */
    private function mayImport(): bool
    {
        $admin = auth('admin')->user();

        return (bool) ($admin?->hasPermission(Permissions::EDIT_CLIENTS) && $admin->hasPermission(Permissions::MANAGE_SERVICES));
    }

    private function priceListKey(Server $server): string
    {
        return 'server_sync_price_list.'.$server->id;
    }
}
