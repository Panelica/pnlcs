<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\SyncsDomainData;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\RegistrarSettings;
use App\Services\DomainRegistrarActions;
use App\Services\DomainService;
use App\Services\Module\ModuleRegistry;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    /** The columns the list screen offers to sort by. */
    private const SORTABLE = ['created_at', 'expiry_date', 'registration_date', 'domain'];

    public function index(Request $request)
    {
        $query = Domain::with('client');
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('registrar')) {
            $query->where('registrar', $request->registrar);
        }
        if ($request->filled('search')) {
            $query->where('domain', 'like', "%{$request->search}%");
        }

        // Only the columns the screen offers. Anything else used to be handed
        // to the query builder as written, and a name that is not a column
        // came back from the database as an error the visitor saw as a broken
        // page.
        $sortField = in_array($request->get('sort'), self::SORTABLE, true)
            ? $request->get('sort')
            : 'created_at';
        $sortDir = strtolower((string) $request->get('dir')) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['domain', 'expiry_date', 'registration_date', 'next_due_date', 'created_at', 'status'];
        if (! in_array($sortField, $allowedSorts)) {
            $sortField = 'created_at';
        }

        $domains = $query->orderBy($sortField, $sortDir)->paginate(25);

        $active = $this->activeRegistrarKeys();
        $registrars = Domain::distinct()->pluck('registrar')->filter()
            ->filter(fn ($r) => in_array(strtolower((string) $r), $active, true))
            ->sortBy(fn ($r) => [strtolower((string) $r) === 'manual' ? 0 : 1, strtolower((string) $r)])
            ->values();
        $statuses = ['active', 'pending', 'grace', 'redemption', 'expired', 'cancelled', 'transferred_away'];

        return view('admin.domains.index', compact('domains', 'registrars', 'statuses'));
    }

    /**
     * The registrar module keys that are switched on (Manual by default,
     * every other registrar only once the operator enabled it).
     *
     * @return array<int, string>
     */
    private function activeRegistrarKeys(): array
    {
        $stored = RegistrarSettings::all()->groupBy('registrar');
        $active = [];

        foreach (app(ModuleRegistry::class)->getRegistrarModules() as $name) {
            $settings = ($stored[$name] ?? collect())->pluck('value', 'setting');

            $on = $name === 'manual'
                ? $settings->get('visible', '1') !== '0'
                : $settings->get('visible') === '1';

            if ($on) {
                $active[] = $name;
            }
        }

        return $active;
    }

    public function show(Domain $domain)
    {
        $domain->load('client', 'order');

        // The registrar holds the lock; the value is shown to the operator so
        // the toggle button can reflect reality rather than a stale column.
        $locked = null;
        $module = app(ModuleRegistry::class)->getRegistrarModule((string) $domain->registrar);

        if ($module) {
            try {
                $locked = $module->getLockStatus($domain);
            } catch (\Throwable) {
                $locked = null;
            }
        }

        $registrarOptions = $this->registrarOptions();

        // What staff can do at the registrar on the customer's behalf: the
        // same actions the client area offers, where the registrar can.
        $actions = app(DomainRegistrarActions::class);
        $canTogglePrivacy = $actions->privacyModule($domain) !== null;
        $canEditContacts = $actions->contactsModule($domain) !== null;
        $canManageGlue = $actions->glueModule($domain) !== null;

        return view('admin.domains.show', compact('domain', 'locked', 'registrarOptions', 'canTogglePrivacy', 'canEditContacts', 'canManageGlue'));
    }

    /**
     * The registrar modules this installation has, as select options. Value and
     * label are both the module's display name so the stored value stays the
     * same shape the modules themselves write.
     *
     * @return array<string, string>
     */
    private function registrarOptions(): array
    {
        $options = [];

        foreach (app(ModuleRegistry::class)->getRegistrarModules() as $key) {
            $module = app(ModuleRegistry::class)->getRegistrarModule($key);
            $name = $module ? $module->getModuleName() : ucfirst($key);
            $options[$name] = $name;
        }

        ksort($options);

        return $options;
    }

    public function updateRegistrar(Request $request, Domain $domain)
    {
        $allowed = array_keys($this->registrarOptions());

        $request->validate([
            'registrar' => ['required', 'string', \Illuminate\Validation\Rule::in($allowed)],
        ]);

        $domain->update(['registrar' => $request->input('registrar')]);

        return back()->with('success', __('admin.domains.registrar_updated'));
    }

    /**
     * Move the domain to another client account, for a customer who sold it
     * or keeps it under another account. Nothing changes at the registry; the
     * WHOIS contact is the new owner's to update.
     *
     * Refused while an unpaid invoice carries the domain: it would stay with
     * the old account and renew a domain that is no longer theirs.
     */
    public function move(Request $request, Domain $domain)
    {
        $target = trim((string) $request->validate(['client' => 'required|string|max:255'])['client']);
        $client = ctype_digit($target)
            ? \App\Models\Client::find((int) $target)
            : \App\Models\Client::where('email', $target)->first();

        if (! $client) {
            return back()->withErrors(['client' => __('admin.domains.move_no_client')]);
        }
        $mover = app(\App\Services\DomainMove::class);
        $problem = $mover->problem($domain, $client);
        if ($problem === 'same_client') {
            return back()->withErrors(['client' => __('admin.domains.move_same_client')]);
        }
        if ($problem === 'open_invoice') {
            return back()->with('error', __('admin.domains.move_open_invoice'));
        }

        $mover->move($domain, $client, auth('admin')->user()?->username);

        return back()->with('success', __('admin.domains.moved', ['client' => $client->full_name ?: $client->email]));
    }

    /**
     * Pull authoritative state (expiry, status, nameservers, lock) back from
     * the registrar. Only registrars that implement SyncsDomainData can do it.
     */
    public function sync(Domain $domain)
    {
        $module = app(ModuleRegistry::class)->getRegistrarModule((string) $domain->registrar);

        if (! $module instanceof SyncsDomainData) {
            return back()->with('error', __('admin.domains.sync_unavailable'));
        }

        try {
            $result = $module->syncDomain($domain);
        } catch (\Throwable $e) {
            $domain->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'error',
            ]);

            return back()->with('error', $e->getMessage());
        }

        if (! ($result['success'] ?? false)) {
            $domain->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'error',
            ]);

            return back()->with('error', $result['message'] ?? __('admin.domains.sync_failed'));
        }

        $changes = [
            'last_sync_at' => now(),
            'last_sync_status' => 'ok',
        ];

        if (! empty($result['expiry_date'])) {
            $changes['expiry_date'] = $result['expiry_date'];

            // The renewal invoice is generated two weeks before the domain
            // actually expires, so the client still has time to pay it.
            try {
                $changes['next_due_date'] = Carbon::parse($result['expiry_date'])
                    ->subDays(14)
                    ->toDateString();
            } catch (\Throwable) {
                // Keep the previous due date if the registrar's date is odd.
            }
        }
        if (! empty($result['status'])) {
            $changes['status'] = $result['status'];
        }
        if (! empty($result['nameservers'])) {
            $changes['nameservers'] = json_encode(array_values($result['nameservers']));
        }
        if (is_bool($result['id_protection'] ?? null)) {
            $changes['id_protection'] = $result['id_protection'];
        }

        $domain->update($changes);

        return back()->with('success', __('admin.domains.synced'));
    }

    public function renew(Request $request, Domain $domain)
    {
        $years = max(1, (int) $request->input('years', 1));

        app(DomainService::class)->renewDomain($domain, $years);

        return back()->with('success', __('admin.domains.renewed', ['domain' => $domain->domain]));
    }

    public function updateNameservers(Request $request, Domain $domain)
    {
        $request->validate([
            'ns' => 'required|array|min:2',
            'ns.*' => 'required|string|max:255',
        ]);

        $result = app(DomainService::class)->updateNameservers($domain, array_values($request->input('ns')));

        if (! ($result['success'] ?? false)) {
            return back()->with('error', $result['message'] ?? __('admin.domains.ns_update_failed'));
        }

        return back()->with('success', __('admin.domains.ns_updated'));
    }

    public function toggleLock(Domain $domain)
    {
        $module = app(ModuleRegistry::class)->getRegistrarModule((string) $domain->registrar);

        if (! $module) {
            return back()->with('error', __('admin.domains.lock_unavailable'));
        }

        try {
            $locked = $module->getLockStatus($domain);
            $ok = $module->toggleLock($domain, ! $locked);
        } catch (\Throwable) {
            $ok = false;
        }

        if (! $ok) {
            return back()->with('error', __('admin.domains.lock_failed'));
        }

        return back()->with('success', $locked ? __('admin.domains.unlocked') : __('admin.domains.locked'));
    }

    public function toggleAutoRenew(Domain $domain)
    {
        $payment = $domain->payment_method === 'none' ? 'banktransfer' : 'none';
        $domain->update(['payment_method' => $payment]);

        return back()->with('success', $payment !== 'none' ? __('admin.domains.autorenew_on') : __('admin.domains.autorenew_off'));
    }

    public function getEppCode(Domain $domain)
    {
        $module = app(ModuleRegistry::class)->getRegistrarModule((string) $domain->registrar);

        if (! $module) {
            return back()->with('error', __('admin.domains.epp_unavailable'));
        }

        try {
            $code = $module->getEPPCode($domain);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('epp_code', $code);
    }

    /**
     * Switch WHOIS privacy at the registrar for the customer. Unlike the
     * client area, staff may switch on privacy that is sold for the
     * extension; turning paid privacy off still stops charging for it.
     */
    public function togglePrivacy(Domain $domain)
    {
        $actions = app(DomainRegistrarActions::class);
        if (! $actions->privacyModule($domain)) {
            return back()->with('error', __('client.domains.privacy_unavailable'));
        }

        $enable = ! $domain->id_protection;
        $result = $actions->setPrivacy($domain, $enable);
        if (! $result['success']) {
            return back()->with('error', trim(__('admin.domains.privacy_failed').' '.$result['message']));
        }

        return back()->with('success', $enable ? __('admin.domains.privacy_on') : __('admin.domains.privacy_off'));
    }

    public function contacts(Domain $domain)
    {
        $actions = app(DomainRegistrarActions::class);
        if (! $actions->contactsModule($domain)) {
            return redirect()->route('admin.domains.show', $domain)->with('error', __('client.domains.contacts_unavailable'));
        }

        ['contact' => $contact, 'fromProfile' => $fromProfile] = $actions->contact($domain);
        $countries = \App\Support\Countries::all();

        return view('admin.domains.contacts', compact('domain', 'contact', 'fromProfile', 'countries'));
    }

    public function updateContacts(Request $request, Domain $domain)
    {
        $actions = app(DomainRegistrarActions::class);
        if (! $actions->contactsModule($domain)) {
            return redirect()->route('admin.domains.show', $domain)->with('error', __('client.domains.contacts_unavailable'));
        }

        $result = $actions->saveContact($domain, $request->validate(DomainRegistrarActions::contactRules()));
        if (! $result['success']) {
            return back()->withInput()->with('error', trim(__('client.domains.contacts_failed').' '.$result['message']));
        }

        return redirect()->route('admin.domains.show', $domain)->with('success', __('client.domains.contacts_saved'));
    }

    public function glue(Domain $domain)
    {
        $actions = app(DomainRegistrarActions::class);
        if (! $actions->glueModule($domain)) {
            return redirect()->route('admin.domains.show', $domain)->with('error', __('client.domains.glue_unavailable'));
        }

        $hosts = $actions->glueHosts($domain);

        return view('admin.domains.glue', compact('domain', 'hosts'));
    }

    public function saveGlue(Request $request, Domain $domain)
    {
        $actions = app(DomainRegistrarActions::class);
        if (! $actions->glueModule($domain)) {
            return redirect()->route('admin.domains.show', $domain)->with('error', __('client.domains.glue_unavailable'));
        }

        $v = $request->validate([
            'host' => ['required', 'string', 'max:100'],
            'ipv4' => ['required', 'ip', 'ipv4'],
            'ipv6' => ['nullable', 'ip', 'ipv6'],
        ]);

        $host = $actions->glueHost($domain, $v['host']);
        if ($host === null) {
            return back()->withInput()->withErrors(['host' => __('client.domains.glue_host_invalid', ['domain' => $domain->domain])]);
        }

        $result = $actions->saveGlue($domain, $host, [$v['ipv4'], $v['ipv6'] ?? null]);
        if (! $result['success']) {
            return back()->withInput()->with('error', trim(__('client.domains.glue_failed').' '.$result['message']));
        }

        return redirect()->route('admin.domains.glue', $domain)->with('success', __('client.domains.glue_saved', ['host' => $host]));
    }

    public function deleteGlue(Request $request, Domain $domain)
    {
        $actions = app(DomainRegistrarActions::class);
        if (! $actions->glueModule($domain)) {
            return redirect()->route('admin.domains.show', $domain)->with('error', __('client.domains.glue_unavailable'));
        }

        $host = $actions->glueHost($domain, (string) $request->validate(['host' => 'required|string|max:100'])['host']);
        if ($host === null) {
            abort(422);
        }

        $result = $actions->deleteGlue($domain, $host);

        return redirect()->route('admin.domains.glue', $domain)->with(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? __('client.domains.glue_deleted', ['host' => $host]) : trim(__('client.domains.glue_failed').' '.$result['message'])
        );
    }
}
