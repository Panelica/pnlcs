<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Affiliate;
use App\Models\Announcement;
use App\Models\ApiCredential;
use App\Models\BannedEmail;
use App\Models\BannedIp;
use App\Models\BillableItem;
use App\Models\Client;
use App\Models\ClientGroup;
use App\Models\ConfigOption;
use App\Models\ConfigOptionGroup;
use App\Models\ConfigOptionLink;
use App\Models\ConfigOptionSub;
use App\Models\Currency;
use App\Models\DomainPricing;
use App\Models\Download;
use App\Models\DownloadCategory;
use App\Models\EmailTemplate;
use App\Models\GatewaySettings;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\NetworkIssue;
use App\Models\NotificationProvider;
use App\Models\NotificationRule;
use App\Models\Pricing;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\ProductBundle;
use App\Models\Promotion;
use App\Models\Quote;
use App\Models\RegistrarSettings;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Models\Setting;
use App\Models\SslModuleSettings;
use App\Models\TaxRule;
use App\Models\TicketDepartment;
use App\Models\TicketEscalation;
use App\Models\TicketSpamFilter;
use App\Models\TicketStatus;
use App\Models\TodoItem;
use App\Models\Transaction;
use App\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ConfigController extends Controller
{
    // ===== STAFF =====

    public function admins()
    {
        return view('admin.config.admins', [
            'admins' => Admin::with('role')->get(),
            'roles' => AdminRole::all(),
        ]);
    }

    public function storeAdmin(Request $request)
    {
        $v = $request->validate([
            'username' => 'required|unique:admins',
            'email' => 'required|email|unique:admins',
            'password' => 'required|min:6',
            'first_name' => 'required',
            'last_name' => 'required',
            'role_id' => 'required|exists:admin_roles,id',
        ]);
        $v['password'] = Hash::make($v['password']);
        Admin::create($v);

        return back()->with('success', __('messages.success.admin_created_successfully'));
    }

    public function updateAdmin(Request $request, Admin $admin)
    {
        $v = $request->validate([
            'username' => 'required|unique:admins,username,'.$admin->id,
            'email' => 'required|email|unique:admins,email,'.$admin->id,
            'first_name' => 'required',
            'last_name' => 'required',
            'role_id' => 'required|exists:admin_roles,id',
            'password' => 'nullable|min:6',
        ]);
        if (empty($v['password'])) {
            unset($v['password']);
        } else {
            $v['password'] = Hash::make($v['password']);
        }
        $admin->update($v);

        return back()->with('success', __('messages.success.admin_updated_successfully'));
    }

    public function destroyAdmin(Admin $admin)
    {
        if ($admin->id === auth('admin')->id()) {
            return back()->with('error', __('messages.error.you_cannot_delete_your_own_account'));
        }
        $admin->delete();

        return back()->with('success', __('messages.success.admin_deleted_successfully'));
    }

    // ===== ADMIN ROLES =====

    public function adminRoles()
    {
        return view('admin.config.admin-roles', [
            'roles' => AdminRole::withCount('admins')->get(),
        ]);
    }

    public function storeRole(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|unique:admin_roles',
            'description' => 'nullable|string',
            'is_full_admin' => 'boolean',
        ]);
        $v['is_full_admin'] = $request->boolean('is_full_admin');
        AdminRole::create($v);

        return back()->with('success', __('messages.success.role_created_successfully'));
    }

    public function updateRole(Request $request, AdminRole $role)
    {
        $v = $request->validate([
            'name' => 'required|unique:admin_roles,name,'.$role->id,
            'description' => 'nullable|string',
            'is_full_admin' => 'boolean',
        ]);
        $v['is_full_admin'] = $request->boolean('is_full_admin');
        $role->update($v);

        return back()->with('success', __('messages.success.role_updated_successfully'));
    }

    public function destroyRole(AdminRole $role)
    {
        if ($role->admins()->count() > 0) {
            return back()->with('error', __('messages.error.cannot_delete_role_it_still_has_admins_assigned'));
        }
        $role->delete();

        return back()->with('success', __('messages.success.role_deleted_successfully'));
    }

    // ===== API CREDENTIALS =====

    public function apiCredentials()
    {
        return view('admin.config.api-credentials', [
            'credentials' => ApiCredential::with('admin')->get(),
        ]);
    }

    public function storeApiCredential(Request $request)
    {
        $secret = Str::random(64);
        ApiCredential::create([
            'admin_id' => auth('admin')->id(),
            'identifier' => Str::random(32),
            'secret' => ApiCredential::hashSecret($secret),
            'description' => $request->description,
            'active' => true,
        ]);

        return back()->with('success', __('messages.success.api_credential_generated'))->with('new_secret', $secret);
    }

    public function destroyApiCredential(ApiCredential $credential)
    {
        $credential->delete();

        return back()->with('success', __('messages.success.api_credential_revoked'));
    }

    // ===== CURRENCIES =====

    public function currencies()
    {
        return view('admin.config.currencies', [
            'currencies' => Currency::all(),
        ]);
    }

    public function storeCurrency(Request $request)
    {
        $v = $request->validate([
            'code' => 'required|string|max:3|unique:currencies',
            'prefix' => 'nullable|string|max:10',
            'suffix' => 'nullable|string|max:10',
            'rate' => 'required|numeric|min:0.00001',
        ]);
        // DB columns are NOT NULL — convert null to empty string
        $v['prefix'] = $v['prefix'] ?? '';
        $v['suffix'] = $v['suffix'] ?? '';
        Currency::create($v);

        return back()->with('success', __('messages.success.currency_created'));
    }

    public function updateCurrency(Request $request, Currency $currency)
    {
        $v = $request->validate([
            'code' => 'required|string|max:3|unique:currencies,code,'.$currency->id,
            'prefix' => 'nullable|string|max:10',
            'suffix' => 'nullable|string|max:10',
            'rate' => 'required|numeric|min:0.00001',
        ]);
        // DB columns are NOT NULL — convert null to empty string
        $v['prefix'] = $v['prefix'] ?? '';
        $v['suffix'] = $v['suffix'] ?? '';
        $currency->update($v);

        return back()->with('success', __('messages.success.currency_updated'));
    }

    public function destroyCurrency(Currency $currency)
    {
        if ($currency->is_default) {
            return back()->with('error', __('messages.error.cannot_delete_the_default_currency'));
        }
        $currency->delete();

        return back()->with('success', __('messages.success.currency_deleted'));
    }

    public function setDefaultCurrency(Currency $currency)
    {
        Currency::query()->update(['is_default' => false]);
        $currency->update(['is_default' => true]);

        return back()->with('success', __('messages.success.currency_set_default'));
    }

    // ===== TAX RULES =====

    public function tax()
    {
        // The screen reads $taxes; passing 'rules' meant it always showed
        // the empty state, however many rules were configured.
        return view('admin.config.tax', [
            'taxes' => TaxRule::orderBy('level')->orderBy('country')->get(),
        ]);
    }

    public function storeTax(Request $request)
    {
        $request->merge(['tax_rate' => $request->tax_rate ?? $request->rate]);
        $v = $request->validate([
            'name' => 'required',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'country' => 'nullable|string|max:2',
            'state' => 'nullable|string',
            'level' => 'nullable|integer|min:1|max:2',
        ]);
        TaxRule::create($v);

        return back()->with('success', __('messages.success.tax_rule_added'));
    }

    public function updateTax(Request $request, TaxRule $taxRule)
    {
        $request->merge(['tax_rate' => $request->tax_rate ?? $request->rate]);
        $v = $request->validate([
            'name' => 'required',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'country' => 'nullable|string|max:2',
            'state' => 'nullable|string',
            'level' => 'nullable|integer|min:1|max:2',
        ]);
        $taxRule->update($v);

        return back()->with('success', __('messages.success.tax_updated'));
    }

    public function destroyTax(TaxRule $taxRule)
    {
        $taxRule->delete();

        return back()->with('success', __('messages.success.tax_deleted'));
    }

    // ===== PROMOTIONS =====

    public function promotions()
    {
        return view('admin.config.promotions', [
            'promotions' => Promotion::orderBy('id', 'desc')->get(),
            'products' => Product::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storePromotion(Request $request)
    {
        $v = $request->validate([
            'code' => 'required|unique:promotions',
            'type' => 'required|in:percentage,fixed_amount,free_setup,price_override,override_recurring',
            'value' => 'required|numeric|min:0',
            'max_uses' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'expiration_date' => 'nullable|date|after_or_equal:start_date',
            'recurring' => 'boolean',
            'notes' => 'nullable|string',
            'applies_to' => 'nullable|array',
            'applies_to.*' => 'integer|exists:products,id',
            'apply_once' => 'boolean',
            'new_signups_only' => 'boolean',
            'existing_client' => 'boolean',
        ]);
        $v['recurring'] = $request->boolean('recurring');
        $v['apply_once'] = $request->boolean('apply_once');
        $v['new_signups_only'] = $request->boolean('new_signups_only');
        $v['existing_client'] = $request->boolean('existing_client');
        $v['applies_to'] = $request->filled('applies_to')
            ? implode(',', $request->input('applies_to'))
            : null;
        Promotion::create($v);

        return back()->with('success', __('messages.success.promotion_created'));
    }

    public function updatePromotion(Request $request, Promotion $promotion)
    {
        $v = $request->validate([
            'code' => 'required|unique:promotions,code,'.$promotion->id,
            'type' => 'required|in:percentage,fixed_amount,free_setup,price_override,override_recurring',
            'value' => 'required|numeric|min:0',
            'max_uses' => 'nullable|integer|min:0',
            'start_date' => 'nullable|date',
            'expiration_date' => 'nullable|date|after_or_equal:start_date',
            'recurring' => 'boolean',
            'notes' => 'nullable|string',
        ]);
        $v['recurring'] = $request->boolean('recurring');
        $promotion->update($v);

        return back()->with('success', __('messages.success.promotion_updated'));
    }

    public function destroyPromotion(Promotion $promotion)
    {
        $promotion->delete();

        return back()->with('success', __('messages.success.promotion_deleted'));
    }

    // ===== SERVERS =====

    public function servers()
    {
        return view('admin.config.servers', [
            'servers' => Server::all(),
            'groups' => ServerGroup::all(),
        ]);
    }

    public function storeServer(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string',
            'hostname' => 'required|string',
            'ip_address' => 'nullable|string',
            'port' => 'nullable|integer',
            'type' => 'nullable|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'access_hash' => 'nullable|string',
            'max_accounts' => 'nullable|integer|min:0',
            'active' => 'boolean',
            'nameserver1' => 'nullable|string',
            'nameserver2' => 'nullable|string',
        ]);
        // The active checkbox sends nothing when unchecked.
        $v['active'] = $request->boolean('active');
        Server::create($v);

        return back()->with('success', __('messages.success.server_created'));
    }

    // ===== DOMAIN PRICING =====

    public function domainPricing()
    {
        return view('admin.config.domain-pricing', [
            'tlds' => DomainPricing::orderBy('sort_order')->get(),
        ]);
    }

    public function storeTld(Request $request)
    {
        $v = $request->validate([
            'extension' => 'required|string|unique:domain_pricing',
            'register_price' => 'required|numeric|min:0',
            'transfer_price' => 'required|numeric|min:0',
            'renew_price' => 'required|numeric|min:0',
            'grace_period' => 'nullable|integer|min:0',
            'min_years' => 'nullable|integer|min:1',
            'max_years' => 'nullable|integer|min:1',
            'auto_registrar' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'enabled' => 'boolean',
        ]);
        $v['enabled'] = $request->boolean('enabled');
        DomainPricing::create($v);

        return back()->with('success', __('messages.success.tld_created'));
    }

    // ===== PAYMENT GATEWAYS / REGISTRARS =====

    public function gateways(ModuleRegistry $registry)
    {
        $stored = GatewaySettings::all()->groupBy('gateway');

        // Every installed module, each asking for the settings it actually
        // reads. The screen used to list the gateways and their fields by
        // hand, which is how PayPal came to be asked for an email address it
        // never looks at.
        $gateways = collect($registry->getGatewayModules())
            ->map(function (string $name) use ($registry, $stored) {
                $module = $registry->getGatewayModule($name);
                $values = ($stored[$name] ?? collect())->pluck('value', 'setting');

                return (object) [
                    'name' => $name,
                    'label' => $module?->getModuleName() ?? ucfirst($name),
                    'fields' => $module?->getConfigFields() ?? [],
                    'values' => $values->toArray(),
                    'active' => (string) ($values['active'] ?? '0') === '1',
                ];
            })
            ->sortBy('label')
            ->values();

        return view('admin.config.gateways', ['gateways' => $gateways]);
    }

    public function registrars()
    {
        // The blade iterates $registrars; it used to receive nothing at all,
        // so the page always claimed no registrars were configured. Mirrors the
        // gateways() shape: every installed module, enriched with its settings.
        $stored = RegistrarSettings::all()->groupBy('registrar');

        $registrars = collect(app(ModuleRegistry::class)->getRegistrarModules())
            ->merge($stored->keys())
            ->unique()
            ->sort()
            ->map(function ($name) use ($stored) {
                $settings = ($stored[$name] ?? collect())->pluck('value', 'setting');

                return (object) [
                    'registrar_name' => $name,
                    'description' => $settings->get('name', ucfirst($name)),
                    'disabled' => $settings->get('visible', '1') === '0',
                    'settings' => $settings->except(['name', 'visible'])->toArray(),
                ];
            })->values();

        return view('admin.config.registrars', ['registrars' => $registrars]);
    }

    // ===== EMAIL TEMPLATES =====

    public function emailTemplates()
    {
        return view('admin.config.email-templates', [
            'templates' => EmailTemplate::orderBy('type')->get(),
        ]);
    }

    // ===== TICKET DEPARTMENTS =====

    public function ticketDepartments()
    {
        return view('admin.config.ticket-departments', [
            'departments' => TicketDepartment::orderBy('sort_order')->get(),
        ]);
    }

    public function storeTicketDepartment(Request $request)
    {
        $v = $request->validate(['name' => 'required', 'email' => 'nullable|email', 'description' => 'nullable|string', 'hidden' => 'boolean']);
        $v['hidden'] = $request->boolean('hidden');
        TicketDepartment::create($v);

        return back()->with('success', __('messages.success.department_created'));
    }

    // ===== TICKET STATUSES =====

    public function ticketStatuses()
    {
        return view('admin.config.ticket-statuses', [
            'statuses' => TicketStatus::orderBy('sort_order')->get(),
        ]);
    }

    // ===== BANNED IPs =====

    public function bannedIps()
    {
        return view('admin.config.banned-ips', [
            'bannedIps' => BannedIp::orderByDesc('id')->get(),
        ]);
    }

    public function storeBannedIp(Request $request)
    {
        BannedIp::create($request->validate(['ip' => 'required', 'reason' => 'nullable|string']));

        return back()->with('success', __('messages.success.banned_ip_created'));
    }

    // ===== BANNED EMAILS =====

    public function bannedEmails()
    {
        return view('admin.config.banned-emails', [
            'bannedEmails' => BannedEmail::orderByDesc('id')->get(),
        ]);
    }

    // ===== ANNOUNCEMENTS =====

    public function announcements()
    {
        return view('admin.config.announcements', [
            'announcements' => Announcement::orderBy('id', 'desc')->get(),
        ]);
    }

    public function storeAnnouncement(Request $request)
    {
        // The admin form posts the content as "body"; the API and older
        // callers use "announcement". Accept either.
        if (! $request->filled('announcement') && $request->filled('body')) {
            $request->merge(['announcement' => $request->input('body')]);
        }
        $v = $request->validate(['title' => 'required', 'announcement' => 'required|string', 'published' => 'boolean']);
        Announcement::create([
            'title' => $v['title'],
            'announcement' => $v['announcement'],
            'published' => $request->boolean('published'),
        ]);

        return back()->with('success', __('messages.success.announcement_published'));
    }

    // ===== DOWNLOADS =====

    public function downloads()
    {
        return view('admin.config.downloads', [
            'categories' => DownloadCategory::with('downloads')->get(),
        ]);
    }

    // ===== KNOWLEDGE BASE =====

    public function knowledgeBase()
    {
        return view('admin.config.knowledge-base', [
            'categories' => KbCategory::orderBy('name')->get(),
            'articles' => KbArticle::with('category')->orderByDesc('id')->get(),
        ]);
    }

    public function storeKbArticle(Request $request)
    {
        $v = $this->validateKbArticle($request);
        KbArticle::create($v);

        return back()->with('success', __('messages.success.article_created'));
    }

    private function validateKbArticle(Request $request): array
    {
        $v = $request->validate([
            'category_id' => 'required|exists:kb_categories,id',
            'title' => 'required|string',
            'article' => 'required|string',
            'published' => 'boolean',
        ]);
        // kb_articles has a "private" column; the form speaks in "published".
        $v['private'] = ! $request->boolean('published');
        unset($v['published']);

        return $v;
    }

    // ===== NETWORK ISSUES =====

    public function networkIssues()
    {
        return view('admin.config.network-issues', [
            'networkIssues' => NetworkIssue::orderBy('id', 'desc')->get(),
        ]);
    }

    // ===== AFFILIATES =====

    public function affiliates()
    {
        return view('admin.config.affiliates', [
            'affiliates' => Affiliate::with('client')->get(),
        ]);
    }

    // ===== QUOTES =====

    public function quotes()
    {
        return view('admin.config.quotes', [
            'quotes' => Quote::with('client')->orderBy('id', 'desc')->paginate(25),
        ]);
    }

    // ===== BILLABLE ITEMS =====

    public function billableItems()
    {
        return view('admin.config.billable-items', [
            'billableItems' => BillableItem::with('client')->orderBy('id', 'desc')->paginate(25),
            'clients' => Client::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_name']),
        ]);
    }

    // ===== TRANSACTIONS =====

    public function transactions()
    {
        return view('admin.config.transactions', [
            'transactions' => Transaction::with('client')->orderBy('id', 'desc')->paginate(25),
        ]);
    }

    // ===== TODO =====

    public function todoList()
    {
        return view('admin.config.todo', [
            'items' => TodoItem::orderBy('id', 'desc')->get(),
        ]);
    }

    public function storeTodo(Request $request)
    {
        TodoItem::create($request->validate(['title' => 'required', 'description' => 'nullable|string', 'due_date' => 'nullable|date']));

        return back()->with('success', __('messages.success.todo_added'));
    }

    // ===== ACTIVITY LOG =====

    public function activityLog()
    {
        return view('admin.config.activity-log', [
            'logs' => ActivityLog::orderBy('id', 'desc')->paginate(50),
        ]);
    }

    // ===== SYSTEM =====

    public function systemDatabase()
    {
        return view('admin.config.system-database');
    }

    public function systemPhpInfo()
    {
        return view('admin.config.system-phpinfo');
    }

    // === Missing CRUD Methods ===

    // Ticket Departments
    public function updateTicketDepartment(Request $request, TicketDepartment $department)
    {
        $v = $request->validate([
            'name' => 'required', 'description' => 'nullable|string', 'email' => 'nullable|email',
            'clients_only' => 'boolean', 'hidden' => 'boolean', 'sort_order' => 'nullable|integer', 'feedback_request' => 'boolean',
            'import_protocol' => 'nullable|in:imap,pop3', 'import_host' => 'nullable|string|max:255',
            'import_port' => 'nullable|integer|min:1|max:65535', 'import_encryption' => 'nullable|in:ssl,tls,none',
            'import_username' => 'nullable|string|max:255', 'import_password' => 'nullable|string|max:255',
            'import_folder' => 'nullable|string|max:255',
        ]);
        $v['import_active'] = $request->boolean('import_active');
        $v['import_delete'] = $request->boolean('import_delete');
        $v['import_allow_unknown'] = $request->boolean('import_allow_unknown');
        // Blank password = keep the stored one
        if (($v['import_password'] ?? '') === '' || $v['import_password'] === null) {
            unset($v['import_password']);
        }
        $department->update($v);

        return back()->with('success', __('messages.success.department_updated'));
    }

    public function destroyTicketDepartment(TicketDepartment $department)
    {
        $department->delete();

        return back()->with('success', __('messages.success.department_deleted'));
    }

    // Ticket Statuses
    public function storeTicketStatus(Request $request)
    {
        $v = $request->validate(['title' => 'required', 'color' => 'nullable|string', 'sort_order' => 'nullable|integer', 'show_active' => 'boolean', 'show_awaiting' => 'boolean', 'auto_close' => 'boolean']);
        $v['show_active'] = $request->boolean('show_active');
        TicketStatus::create($v);

        return back()->with('success', __('messages.success.ticket_status_created'));
    }

    public function updateTicketStatus(Request $request, TicketStatus $status)
    {
        $v = $request->validate(['title' => 'required', 'color' => 'nullable|string', 'sort_order' => 'nullable|integer', 'show_active' => 'boolean', 'show_awaiting' => 'boolean', 'auto_close' => 'boolean']);
        $v['show_active'] = $request->boolean('show_active');
        $status->update($v);

        return back()->with('success', __('messages.success.ticket_status_updated'));
    }

    public function destroyTicketStatus(TicketStatus $status)
    {
        $status->delete();

        return back()->with('success', __('messages.success.ticket_status_deleted'));
    }

    // Email Templates
    public function updateEmailTemplate(Request $request, EmailTemplate $template)
    {
        $validated = $request->validate(['type' => 'nullable|string', 'name' => 'nullable|string', 'subject' => 'nullable|string', 'message' => 'nullable|string', 'from_name' => 'nullable|string', 'from_email' => 'nullable|email', 'disabled' => 'boolean']);

        // Saving makes the template the operator's own, which is what lets
        // their wording replace the built-in design when the mail goes out.
        $validated['custom'] = true;

        $template->update($validated);

        return back()->with('success', __('messages.success.template_updated'));
    }

    // Servers
    public function updateServer(Request $request, Server $server)
    {
        $v = $request->validate(['name' => 'required', 'hostname' => 'required', 'ip_address' => 'nullable', 'port' => 'nullable|integer', 'type' => 'nullable|string', 'username' => 'nullable|string', 'password' => 'nullable|string', 'access_hash' => 'nullable|string', 'max_accounts' => 'nullable|integer', 'active' => 'boolean', 'nameserver1' => 'nullable', 'nameserver2' => 'nullable']);
        // Blank credential fields mean "keep the stored value" (the form promises this).
        foreach (['password', 'access_hash'] as $secret) {
            if (! $request->filled($secret)) {
                unset($v[$secret]);
            }
        }
        $v['active'] = $request->boolean('active');
        $server->update($v);

        return back()->with('success', __('messages.success.server_updated'));
    }

    public function destroyServer(Server $server)
    {
        $server->delete();

        return back()->with('success', __('messages.success.server_deleted'));
    }

    public function testServerConnection(Server $server)
    {
        $host = $server->hostname ?? $server->ip_address;
        $port = $server->port ?? match ($server->type) {
            'panelica' => 8443, 'cpanel' => 2087, 'plesk' => 8443, 'directadmin' => 2222, default => 22
        };
        if (empty($host)) {
            return back()->with('error', __('admin.messages.no_hostname'));
        }
        $start = microtime(true);
        $conn = @fsockopen($host, $port, $errno, $errstr, 5);
        $elapsed = round((microtime(true) - $start) * 1000);
        if ($conn) {
            fclose($conn);

            return back()->with('success', __('admin.messages.connection_success', ['host' => $host, 'port' => $port, 'elapsed' => $elapsed, 'module' => $server->type ?? 'custom']));
        }

        return back()->with('error', __('admin.messages.connection_failed', ['host' => $host, 'port' => $port, 'error' => $errstr, 'errno' => $errno, 'elapsed' => $elapsed]));
    }

    // Server Groups
    public function serverGroups()
    {
        return view('admin.config.server-groups', [
            'serverGroups' => ServerGroup::with('servers:id,name,type')->withCount('servers')->get(),
            'allServers' => Server::orderBy('name')->get(['id', 'name', 'type']),
        ]);
    }

    public function storeServerGroup(Request $request)
    {
        $v = $this->validateServerGroup($request);
        $group = ServerGroup::create($v);
        $group->servers()->sync($v['server_ids'] ?? []);

        return back()->with('success', __('messages.success.server_group_created'));
    }

    public function updateServerGroup(Request $request, ServerGroup $serverGroup)
    {
        $v = $this->validateServerGroup($request);
        $serverGroup->update($v);
        $serverGroup->servers()->sync($v['server_ids'] ?? []);

        return back()->with('success', __('messages.success.server_group_updated'));
    }

    private function validateServerGroup(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string',
            'fill_type' => 'required|in:fill,round_robin',
            'server_ids' => 'nullable|array',
            'server_ids.*' => 'integer|exists:servers,id',
        ]);
    }

    public function destroyServerGroup(ServerGroup $serverGroup)
    {
        $serverGroup->delete();

        return back()->with('success', __('messages.success.server_group_deleted'));
    }

    // Announcements
    public function updateAnnouncement(Request $request, Announcement $announcement)
    {
        $v = $request->validate(['title' => 'required', 'published' => 'boolean']);
        $v['announcement'] = $request->body ?? $request->announcement ?? $announcement->announcement;
        $announcement->update($v);

        return back()->with('success', __('messages.success.announcement_updated'));
    }

    public function destroyAnnouncement(Announcement $announcement)
    {
        $announcement->delete();

        return back()->with('success', __('messages.success.announcement_deleted'));
    }

    // Knowledge Base
    public function storeKbCategory(Request $request)
    {
        KbCategory::create($request->validate(['name' => 'required', 'description' => 'nullable|string', 'sort_order' => 'nullable|integer']));

        return back()->with('success', __('messages.success.category_created'));
    }

    public function updateKbArticle(Request $request, KbArticle $article)
    {
        $article->update($this->validateKbArticle($request));

        return back()->with('success', __('messages.success.article_updated'));
    }

    public function destroyKbArticle(KbArticle $article)
    {
        $article->delete();

        return back()->with('success', __('messages.success.article_deleted'));
    }

    // Downloads
    public function storeDownloadCategory(Request $request)
    {
        DownloadCategory::create($request->validate(['name' => 'required', 'description' => 'nullable|string']));

        return back()->with('success', __('messages.success.category_created'));
    }

    public function destroyDownloadCategory(DownloadCategory $category)
    {
        // downloads.category_id is constrained with cascadeOnDelete — the
        // category's downloads are removed with it.
        $category->delete();

        return back()->with('success', __('messages.success.category_deleted'));
    }

    public function storeDownload(Request $request)
    {
        $v = $request->validate([
            'category_id' => 'required|exists:download_categories,id',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'location' => 'required|string',
            'published' => 'boolean',
        ]);
        $v['hidden'] = ! $request->boolean('published');
        unset($v['published']);
        Download::create($v);

        return back()->with('success', __('messages.success.download_created'));
    }

    public function destroyDownload(Download $download)
    {
        $download->delete();

        return back()->with('success', __('messages.success.download_deleted'));
    }

    // Network Issues
    public function storeNetworkIssue(Request $request)
    {
        NetworkIssue::create($request->validate(['title' => 'required', 'description' => 'nullable|string', 'type' => 'nullable|string', 'status' => 'required', 'affected' => 'nullable|string', 'start_date' => 'nullable|date', 'end_date' => 'nullable|date']));

        return back()->with('success', __('messages.success.network_issue_created'));
    }

    public function updateNetworkIssue(Request $request, NetworkIssue $issue)
    {
        $issue->update($request->validate(['title' => 'required', 'description' => 'nullable|string', 'type' => 'nullable|string', 'status' => 'required', 'affected' => 'nullable|string', 'start_date' => 'nullable|date', 'end_date' => 'nullable|date']));

        return back()->with('success', __('messages.success.network_issue_updated'));
    }

    public function destroyNetworkIssue(NetworkIssue $issue)
    {
        $issue->delete();

        return back()->with('success', __('messages.success.network_issue_deleted'));
    }

    // Banned IPs/Emails
    public function destroyBannedIp(BannedIp $bannedIp)
    {
        $bannedIp->delete();

        return back()->with('success', __('messages.success.ip_unbanned'));
    }

    public function storeBannedEmail(Request $request)
    {
        // banned_emails has no "type" column; the form field is named "domain"
        // (it accepts a full address or a domain pattern).
        BannedEmail::create($request->validate(['domain' => 'required|string', 'reason' => 'nullable|string']));

        return back()->with('success', __('messages.success.email_banned'));
    }

    public function destroyBannedEmail(BannedEmail $bannedEmail)
    {
        $bannedEmail->delete();

        return back()->with('success', __('messages.success.email_unbanned'));
    }

    // To-Do
    public function updateTodo(Request $request, TodoItem $todo)
    {
        $todo->update($request->validate(['title' => 'required', 'description' => 'nullable|string', 'status' => 'nullable|string', 'due_date' => 'nullable|date']));

        return back()->with('success', __('messages.success.todo_updated'));
    }

    public function destroyTodo(TodoItem $todo)
    {
        $todo->delete();

        return back()->with('success', __('messages.success.todo_deleted'));
    }

    // Billable Items
    public function storeBillableItem(Request $request)
    {
        BillableItem::create($request->validate(['client_id' => 'required|exists:clients,id', 'description' => 'required', 'amount' => 'required|numeric', 'due_date' => 'nullable|date']));

        return back()->with('success', __('messages.success.billable_item_created'));
    }

    public function destroyBillableItem(BillableItem $item)
    {
        $item->delete();

        return back()->with('success', __('messages.success.billable_item_deleted'));
    }

    // Domain Pricing
    public function updateTld(Request $request, DomainPricing $domainPricing)
    {
        $v = $request->validate([
            'extension' => 'required|string|unique:domain_pricing,extension,'.$domainPricing->id,
            'register_price' => 'nullable|numeric|min:0',
            'transfer_price' => 'nullable|numeric|min:0',
            'renew_price' => 'nullable|numeric|min:0',
            'grace_period' => 'nullable|integer|min:0',
            'min_years' => 'nullable|integer|min:1',
            'max_years' => 'nullable|integer|min:1',
            'auto_registrar' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'enabled' => 'boolean',
        ]);
        $v['enabled'] = $request->boolean('enabled');
        $domainPricing->update($v);

        return back()->with('success', __('messages.success.tld_updated'));
    }

    public function destroyTld(DomainPricing $domainPricing)
    {
        $domainPricing->delete();

        return back()->with('success', __('messages.success.tld_deleted'));
    }

    // Gateway/Registrar settings
    public function updateGatewaySettings(Request $request, string $gateway)
    {
        $settings = $request->input('settings', []);
        $json = $request->input('settings_json');
        if ($json && empty($settings)) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }

        // An unticked checkbox posts nothing, so it has to be written as off
        // rather than left as it was.
        $settings['active'] = $request->boolean('active') ? '1' : '0';

        foreach ($settings as $key => $value) {
            GatewaySettings::updateOrCreate(
                ['gateway' => $gateway, 'setting' => $key],
                ['value' => $value ?? '']
            );
        }

        return back()->with('success', __('admin.messages.gateway_updated'));
    }

    public function updateRegistrarSettings(Request $request, string $registrar)
    {
        // Mirrors updateGatewaySettings: individual settings[key] inputs, with
        // an optional raw-JSON textarea as fallback. This method used to be an
        // empty stub that reported success without persisting anything.
        $settings = $request->input('settings', []);
        $json = $request->input('settings_json');
        if ($json && empty($settings)) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }
        foreach ($settings as $key => $value) {
            RegistrarSettings::updateOrCreate(
                ['registrar' => $registrar, 'setting' => $key],
                ['value' => $value ?? '']
            );
        }

        return back()->with('success', __('messages.success.registrar_updated'));
    }

    // ===== AUTOMATION =====
    public function automation()
    {
        return view('admin.config.automation');
    }

    // ===== CLIENT GROUPS =====
    public function clientGroups()
    {
        return view('admin.config.client-groups', ['groups' => ClientGroup::withCount('clients')->get()]);
    }

    public function storeClientGroup(Request $request)
    {
        ClientGroup::create($request->validate(['name' => 'required', 'color' => 'nullable|string|max:7', 'discount_percent' => 'nullable|numeric|min:0|max:100']));

        return back()->with('success', __('admin.messages.client_group_created'));
    }

    public function updateClientGroup(Request $request, ClientGroup $group)
    {
        $group->update($request->validate(['name' => 'required', 'color' => 'nullable|string|max:7', 'discount_percent' => 'nullable|numeric|min:0|max:100']));

        return back()->with('success', __('admin.messages.client_group_updated'));
    }

    public function destroyClientGroup(ClientGroup $group)
    {
        $group->delete();

        return back()->with('success', __('admin.messages.client_group_deleted'));
    }

    // ===== API DOCS =====

    public function apiDocs()
    {
        return view('admin.api-docs');
    }

    // ===== SSL MODULES =====
    public function sslModules()
    {
        $registry = app(ModuleRegistry::class);
        $modules = [];
        $settings = [];

        foreach ($registry->getSslModules() as $name) {
            $module = $registry->getSslModule($name);
            if ($module) {
                $modules[$name] = $module;
                $settings[$name] = SslModuleSettings::getForModule($name);
            }
        }

        return view('admin.config.ssl-modules', compact('modules', 'settings'));
    }

    public function updateSslModuleSettings(Request $request, string $module)
    {
        $settings = $request->input('settings', []);

        foreach ($settings as $key => $value) {
            SslModuleSettings::setSetting($module, $key, $value);
        }

        return back()->with('success', __('messages.success.ssl_module_updated'));
    }

    public function testSslConnection(string $module)
    {
        $registry = app(ModuleRegistry::class);
        $sslModule = $registry->getSslModule($module);

        if (! $sslModule) {
            return back()->with('error', __('messages.error.ssl_module_not_found'));
        }

        $success = $sslModule->testConnection();

        return back()->with(
            $success ? 'success' : 'error',
            $success ? 'Connection successful!' : 'Connection failed. Please check your credentials.'
        );
    }

    // ===== CONFIG OPTIONS =====

    public function configOptions()
    {
        $groups = ConfigOptionGroup::with(['options.subs.pricing', 'productLinks'])->get();
        $products = Product::orderBy('name')->get(['id', 'name']);

        return view('admin.config.config-options', compact('groups', 'products'));
    }

    /** Which products offer this option group. */
    public function linkConfigOptionGroup(Request $request, int $id)
    {
        $group = ConfigOptionGroup::findOrFail($id);
        $v = $request->validate([
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        ConfigOptionLink::where('group_id', $group->id)->delete();
        foreach ($v['product_ids'] ?? [] as $productId) {
            ConfigOptionLink::create(['group_id' => $group->id, 'product_id' => $productId]);
        }

        return back()->with('success', __('admin.messages.config_option_group_linked'));
    }

    public function storeConfigOptionGroup(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        ConfigOptionGroup::create($validated);

        return back()->with('success', __('admin.messages.config_option_group_created'));
    }

    public function updateConfigOptionGroup(Request $request, $id)
    {
        $group = ConfigOptionGroup::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        $group->update($validated);

        return back()->with('success', __('admin.messages.config_option_group_updated'));
    }

    public function deleteConfigOptionGroup($id)
    {
        ConfigOptionGroup::findOrFail($id)->delete();

        return back()->with('success', __('admin.messages.config_option_group_deleted'));
    }

    public function storeConfigOption(Request $request)
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:config_option_groups,id',
            'option_name' => 'required|string|max:255',
            'option_type' => 'required|in:dropdown,radio,checkbox,quantity,text',
            'sort_order' => 'nullable|integer',
        ]);
        ConfigOption::create($validated);

        return back()->with('success', __('admin.messages.config_option_created'));
    }

    public function deleteConfigOption($id)
    {
        ConfigOption::findOrFail($id)->delete();

        return back()->with('success', __('admin.messages.config_option_deleted'));
    }

    public function storeConfigOptionSub(Request $request)
    {
        $validated = $request->validate([
            'config_id' => 'required|exists:config_options,id',
            'option_name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer',
            'monthly' => 'nullable|numeric|min:0',
            'quarterly' => 'nullable|numeric|min:0',
            'semiannually' => 'nullable|numeric|min:0',
            'annually' => 'nullable|numeric|min:0',
            'biennially' => 'nullable|numeric|min:0',
            'triennially' => 'nullable|numeric|min:0',
        ]);

        $sub = ConfigOptionSub::create([
            'config_id' => $validated['config_id'],
            'option_name' => $validated['option_name'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        // Prices live in the shared pricing table, the same shape products use.
        // Without this the customer could pick an option and be charged nothing.
        $currency = Currency::getDefault();
        Pricing::updateOrCreate(
            ['type' => ConfigOptionSub::PRICING_TYPE, 'rel_id' => $sub->id, 'currency_id' => $currency?->id ?? 1],
            [
                'monthly' => (float) ($request->input('monthly') ?? 0),
                'quarterly' => (float) ($request->input('quarterly') ?? 0),
                'semiannually' => (float) ($request->input('semiannually') ?? 0),
                'annually' => (float) ($request->input('annually') ?? 0),
                'biennially' => (float) ($request->input('biennially') ?? 0),
                'triennially' => (float) ($request->input('triennially') ?? 0),
            ]
        );

        return back()->with('success', __('admin.messages.sub_option_created'));
    }

    public function deleteConfigOptionSub($id)
    {
        ConfigOptionSub::findOrFail($id)->delete();

        return back()->with('success', __('admin.messages.sub_option_deleted'));
    }

    // ===== TICKET ESCALATION =====

    public function ticketEscalation()
    {
        $rules = TicketEscalation::all();
        $departments = TicketDepartment::all();
        $admins = Admin::where('is_disabled', false)->get();

        return view('admin.config.ticket-escalation', compact('rules', 'departments', 'admins'));
    }

    public function storeTicketEscalation(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'departments' => 'nullable|json',
            'statuses' => 'nullable|json',
            'priorities' => 'nullable|json',
            'time_elapsed' => 'required|integer|min:1',
            'new_department_id' => 'nullable|exists:ticket_departments,id',
            'new_priority' => 'nullable|string',
            'flag_to' => 'nullable|exists:admins,id',
            'notify' => 'boolean',
            'add_reply' => 'nullable|string',
        ]);
        $validated['notify'] = $request->boolean('notify');
        TicketEscalation::create($validated);

        return back()->with('success', __('admin.messages.escalation_rule_created'));
    }

    public function updateTicketEscalation(Request $request, $id)
    {
        $rule = TicketEscalation::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'departments' => 'nullable|json',
            'statuses' => 'nullable|json',
            'priorities' => 'nullable|json',
            'time_elapsed' => 'required|integer|min:1',
            'new_department_id' => 'nullable|exists:ticket_departments,id',
            'new_priority' => 'nullable|string',
            'flag_to' => 'nullable|exists:admins,id',
            'notify' => 'boolean',
            'add_reply' => 'nullable|string',
        ]);
        $validated['notify'] = $request->boolean('notify');
        $rule->update($validated);

        return back()->with('success', __('admin.messages.escalation_rule_updated'));
    }

    public function deleteTicketEscalation($id)
    {
        TicketEscalation::findOrFail($id)->delete();

        return back()->with('success', __('admin.messages.escalation_rule_deleted'));
    }

    // ===== NOTIFICATION CHANNELS (Phase 7) =====

    public function notifications()
    {
        $providers = NotificationProvider::with('rules')->get();

        // Every dispatchable event, so the operator can subscribe to the ones
        // that matter most — a failed backup, provisioning that gave up.
        $eventTypes = \App\Services\NotificationService::eventTypes();

        return view('admin.config.notifications', compact('providers', 'eventTypes'));
    }

    public function storeNotificationProvider(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:email,slack,webhook',
            'settings' => 'nullable|array',
            'active' => 'boolean',
        ]);
        $v['active'] = $request->boolean('active');
        $v['settings'] = $request->input('settings', []);
        NotificationProvider::create($v);

        return back()->with('success', __('admin.messages.notification_provider_created'));
    }

    public function updateNotificationProvider(Request $request, $id)
    {
        $provider = NotificationProvider::findOrFail($id);
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:email,slack,webhook',
            'settings' => 'nullable|array',
            'active' => 'boolean',
        ]);
        $v['active'] = $request->boolean('active');
        $v['settings'] = $request->input('settings', []);
        $provider->update($v);

        return back()->with('success', __('admin.messages.notification_provider_updated'));
    }

    public function destroyNotificationProvider($id)
    {
        $provider = NotificationProvider::findOrFail($id);
        $provider->rules()->delete();
        $provider->delete();

        return back()->with('success', __('admin.messages.notification_provider_deleted'));
    }

    public function storeNotificationRule(Request $request)
    {
        $v = $request->validate([
            'event' => 'required|string|max:255',
            'provider_id' => 'required|exists:notification_providers,id',
            'conditions' => 'nullable|array',
            'active' => 'boolean',
        ]);
        $v['active'] = $request->boolean('active');
        $v['conditions'] = $request->input('conditions', []);
        NotificationRule::create($v);

        return back()->with('success', __('admin.messages.notification_rule_created'));
    }

    public function destroyNotificationRule($id)
    {
        NotificationRule::findOrFail($id)->delete();

        return back()->with('success', __('admin.messages.notification_rule_deleted'));
    }

    // ===== TICKET SPAM FILTER (Phase 12) =====

    public function ticketSpam()
    {
        $emailFilters = TicketSpamFilter::where('type', 'email')->pluck('content')->implode("\n");
        $keywordFilters = TicketSpamFilter::where('type', 'keyword')->pluck('content')->implode("\n");

        return view('admin.config.ticket-spam', [
            'bannedEmails' => $emailFilters,
            'bannedKeywords' => $keywordFilters,
            'maxPerHour' => Setting::get('TicketSpamMaxPerHour', 5),
            'filters' => TicketSpamFilter::orderBy('id', 'desc')->get(),
        ]);
    }

    public function updateTicketSpam(Request $request)
    {
        // Sync email patterns
        $emails = array_filter(array_map('trim', explode("\n", $request->input('banned_emails', ''))));
        TicketSpamFilter::where('type', 'email')->delete();
        foreach ($emails as $pattern) {
            if ($pattern) {
                TicketSpamFilter::create(['type' => 'email', 'content' => $pattern]);
            }
        }

        // Sync keyword patterns
        $keywords = array_filter(array_map('trim', explode("\n", $request->input('banned_keywords', ''))));
        TicketSpamFilter::where('type', 'keyword')->delete();
        foreach ($keywords as $keyword) {
            if ($keyword) {
                TicketSpamFilter::create(['type' => 'keyword', 'content' => $keyword]);
            }
        }

        Setting::set('TicketSpamMaxPerHour', $request->input('max_per_hour', 5), 'tickets');

        return back()->with('success', __('admin.messages.spam_filter_updated'));
    }

    public function storeTicketSpamFilter(Request $request)
    {
        $v = $request->validate([
            'type' => 'required|in:email,keyword',
            'content' => 'required|string|max:255',
        ]);
        TicketSpamFilter::create($v);

        return back()->with('success', __('admin.messages.spam_filter_added'));
    }

    public function destroyTicketSpamFilter($id)
    {
        TicketSpamFilter::findOrFail($id)->delete();

        return back()->with('success', __('admin.messages.spam_filter_removed'));
    }

    // ===== PRODUCT ADDONS (Phase 14) =====

    public function addons()
    {
        $addons = ProductAddon::with('pricing')->orderBy('sort_order')->get();
        $products = Product::orderBy('name')->get();

        return view('admin.config.addons', compact('addons', 'products'));
    }

    public function storeAddon(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'hidden' => 'boolean',
            'tax' => 'boolean',
        ]);
        $v['hidden'] = $request->boolean('hidden');
        $v['tax'] = $request->boolean('tax');
        $v['sort_order'] = $v['sort_order'] ?? 0;

        // Store applicable products as comma-separated IDs
        $packages = $request->input('packages', []);
        $v['packages'] = ! empty($packages) ? implode(',', $packages) : null;

        $addon = ProductAddon::create($v);
        $this->saveAddonPricing($addon, $request);

        return back()->with('success', __('admin.messages.addon_created'));
    }

    public function updateAddon(Request $request, $id)
    {
        $addon = ProductAddon::findOrFail($id);
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'hidden' => 'boolean',
            'retired' => 'boolean',
            'tax' => 'boolean',
        ]);
        $v['hidden'] = $request->boolean('hidden');
        $v['retired'] = $request->boolean('retired');
        $v['tax'] = $request->boolean('tax');

        // Only the create form used to send this, so saving an edit wiped the
        // addon's product list.
        if ($request->has('packages')) {
            $packages = $request->input('packages', []);
            $v['packages'] = ! empty($packages) ? implode(',', $packages) : null;
        }

        $addon->update($v);
        $this->saveAddonPricing($addon, $request);

        return back()->with('success', __('admin.messages.addon_updated'));
    }

    /**
     * Write the addon's per-cycle prices. An addon with no price is free, which
     * is a valid thing to sell, so a blank field means zero rather than "leave
     * whatever was there".
     */
    private function saveAddonPricing(ProductAddon $addon, Request $request): void
    {
        $cycles = ['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially'];
        $prices = (array) $request->input('pricing', []);

        if ($prices === []) {
            return;
        }

        $values = [];
        foreach ($cycles as $cycle) {
            $values[$cycle] = round((float) ($prices[$cycle] ?? 0), 2);
        }

        Pricing::updateOrCreate(
            [
                'type' => ProductAddon::PRICING_TYPE,
                'rel_id' => $addon->id,
                'currency_id' => Currency::getDefault()?->id,
            ],
            $values
        );
    }

    public function destroyAddon($id)
    {
        ProductAddon::findOrFail($id)->delete();

        return back()->with('success', __('admin.messages.addon_deleted'));
    }

    // ===== PRODUCT BUNDLES (Phase 15) =====

    public function bundles()
    {
        $bundles = ProductBundle::with(['items.product'])->orderBy('name')->get();
        $products = Product::with('group')->orderBy('name')->get();

        return view('admin.config.bundles', compact('bundles', 'products'));
    }

    public function storeBundle(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'product_ids' => 'required|array|min:2',
            'product_ids.*' => 'exists:products,id',
        ]);

        $bundle = ProductBundle::create([
            'name' => $v['name'],
            'description' => $v['description'] ?? null,
            'discount_type' => $v['discount_type'],
            'discount_value' => $v['discount_value'],
            'is_active' => $request->boolean('is_active'),
        ]);

        foreach ($v['product_ids'] as $productId) {
            $bundle->items()->create([
                'item_type' => 'product',
                'item_id' => $productId,
                'qty' => 1,
            ]);
        }

        return back()->with('success', __('admin.messages.bundle_created'));
    }

    public function destroyBundle($id)
    {
        $bundle = ProductBundle::findOrFail($id);
        $bundle->items()->delete();
        $bundle->delete();

        return back()->with('success', __('admin.messages.bundle_deleted'));
    }
}
