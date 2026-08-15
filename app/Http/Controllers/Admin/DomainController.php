<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\SyncsDomainData;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Services\DomainService;
use App\Services\Module\ModuleRegistry;
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

        $registrars = Domain::distinct()->pluck('registrar')->filter()->sort()->values();
        $statuses = ['active', 'pending', 'grace', 'redemption', 'expired', 'cancelled', 'transferred_away'];

        return view('admin.domains.index', compact('domains', 'registrars', 'statuses'));
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

        return view('admin.domains.show', compact('domain', 'locked', 'registrarOptions'));
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
        $allowed = array_map('strtolower', array_keys($this->registrarOptions()));

        $request->validate([
            'registrar' => ['required', 'string', \Illuminate\Validation\Rule::in($allowed)],
        ]);

        $domain->update(['registrar' => $request->input('registrar')]);

        return back()->with('success', __('admin.domains.registrar_updated'));
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
            return back()->with('error', $e->getMessage());
        }

        if (! ($result['success'] ?? false)) {
            return back()->with('error', $result['message'] ?? __('admin.domains.sync_failed'));
        }

        $changes = [];
        if (! empty($result['expiry_date'])) {
            $changes['expiry_date'] = $result['expiry_date'];
        }
        if (! empty($result['status'])) {
            $changes['status'] = $result['status'];
        }
        if (! empty($result['nameservers'])) {
            $changes['nameservers'] = json_encode(array_values($result['nameservers']));
        }

        if ($changes) {
            $domain->update($changes);
        }

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
}
