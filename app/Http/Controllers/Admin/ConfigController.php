<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Affiliate;
use App\Models\Announcement;
use App\Models\ApiCredential;
use App\Models\BannedEmail;
use App\Models\BannedIp;
use App\Models\BillableItem;
use App\Models\Currency;
use App\Models\DomainPricing;
use App\Models\EmailTemplate;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\NetworkIssue;
use App\Models\Promotion;
use App\Models\Quote;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Models\TaxRule;
use App\Models\TicketDepartment;
use App\Models\TicketStatus;
use App\Models\TodoItem;
use App\Models\Transaction;
use App\Models\ActivityLog;
use App\Models\ClientGroup;
use App\Models\GatewaySettings;
use App\Models\Download;
use App\Models\DownloadCategory;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\ConfigOptionGroup;
use App\Models\ConfigOption;
use App\Models\ConfigOptionSub;
use App\Models\TicketEscalation;


class ConfigController extends Controller
{
    // ===== STAFF =====

    public function admins()
    {
        return view('admin.config.admins', [
            'admins' => Admin::with('role')->get(),
            'roles'  => AdminRole::all(),
        ]);
    }

    public function storeAdmin(Request $request)
    {
        $v = $request->validate([
            'username'   => 'required|unique:admins',
            'email'      => 'required|email|unique:admins',
            'password'   => 'required|min:6',
            'first_name' => 'required',
            'last_name'  => 'required',
            'role_id'    => 'required|exists:admin_roles,id',
        ]);
        $v['password'] = Hash::make($v['password']);
        Admin::create($v);
        return back()->with('success', __('messages.success.admin_created_successfully'));
    }

    public function updateAdmin(Request $request, Admin $admin)
    {
        $v = $request->validate([
            'username'   => 'required|unique:admins,username,' . $admin->id,
            'email'      => 'required|email|unique:admins,email,' . $admin->id,
            'first_name' => 'required',
            'last_name'  => 'required',
            'role_id'    => 'required|exists:admin_roles,id',
            'password'   => 'nullable|min:6',
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
            'name'          => 'required|unique:admin_roles',
            'description'   => 'nullable|string',
            'is_full_admin' => 'boolean',
        ]);
        $v['is_full_admin'] = $request->boolean('is_full_admin');
        AdminRole::create($v);
        return back()->with('success', __('messages.success.role_created_successfully'));
    }

    public function updateRole(Request $request, AdminRole $role)
    {
        $v = $request->validate([
            'name'          => 'required|unique:admin_roles,name,' . $role->id,
            'description'   => 'nullable|string',
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
            'admin_id'    => auth('admin')->id(),
            'identifier'  => Str::random(32),
            'secret'      => $secret,
            'description' => $request->description,
            'active'      => true,
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
            'code'   => 'required|string|max:3|unique:currencies',
            'prefix' => 'nullable|string|max:10',
            'suffix' => 'nullable|string|max:10',
            'rate'   => 'required|numeric|min:0.00001',
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
            'code'   => 'required|string|max:3|unique:currencies,code,' . $currency->id,
            'prefix' => 'nullable|string|max:10',
            'suffix' => 'nullable|string|max:10',
            'rate'   => 'required|numeric|min:0.00001',
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
        return view('admin.config.tax', [
            'rules' => TaxRule::all(),
        ]);
    }

    public function storeTax(Request $request)
    {
        $request->merge(["tax_rate" => $request->tax_rate ?? $request->rate]);
        $v = $request->validate([
            'name'     => 'required',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'country'  => 'nullable|string|max:2',
            'state'    => 'nullable|string',
            'level'    => 'nullable|integer|min:1|max:2',
        ]);
        TaxRule::create($v);
        return back()->with('success', __('messages.success.tax_rule_added'));
    }

    public function updateTax(Request $request, TaxRule $taxRule)
    {
        $request->merge(["tax_rate" => $request->tax_rate ?? $request->rate]);
        $v = $request->validate([
            'name'     => 'required',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'country'  => 'nullable|string|max:2',
            'state'    => 'nullable|string',
            'level'    => 'nullable|integer|min:1|max:2',
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
        ]);
    }

    public function storePromotion(Request $request)
    {
        $v = $request->validate([
            'code'            => 'required|unique:promotions',
            'type'            => 'required|in:percentage,fixed_amount,free_setup,price_override,override_recurring',
            'value'           => 'required|numeric|min:0',
            'max_uses'        => 'nullable|integer|min:0',
            'start_date'      => 'nullable|date',
            'expiration_date' => 'nullable|date|after_or_equal:start_date',
            'recurring'       => 'boolean',
            'notes'           => 'nullable|string',
        ]);
        $v['recurring'] = $request->boolean('recurring');
        Promotion::create($v);
        return back()->with('success', __('messages.success.promotion_created'));
    }

    public function updatePromotion(Request $request, Promotion $promotion)
    {
        $v = $request->validate([
            'code'            => 'required|unique:promotions,code,' . $promotion->id,
            'type'            => 'required|in:percentage,fixed_amount,free_setup,price_override,override_recurring',
            'value'           => 'required|numeric|min:0',
            'max_uses'        => 'nullable|integer|min:0',
            'start_date'      => 'nullable|date',
            'expiration_date' => 'nullable|date|after_or_equal:start_date',
            'recurring'       => 'boolean',
            'notes'           => 'nullable|string',
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
            'groups'  => ServerGroup::all(),
        ]);
    }

    public function storeServer(Request $request)
    {
        $v = $request->validate(['name' => 'required', 'hostname' => 'required', 'type' => 'nullable|string']);
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
        $v = $request->validate(['extension' => 'required|string|unique:domain_pricing']);
        DomainPricing::create($v);
        return back()->with('success', __('messages.success.tld_created'));
    }

    // ===== PAYMENT GATEWAYS / REGISTRARS =====

    public function gateways() {
        $rows = GatewaySettings::orderBy("sort_order")->get();
        $gateways = $rows->groupBy("gateway")->map(function ($items, $key) {
            $settings = $items->pluck("value", "setting");
            return (object) [
                "gateway_name" => $key,
                "description" => $settings->get("name", ucfirst($key)),
                "type" => $settings->get("type", "online"),
                "disabled" => $settings->get("visible", "1") === "0",
                "order_num" => $items->first()->sort_order ?? 0,
                "settings" => $settings->except(["name", "visible", "type"])->toArray(),
            ];
        })->sortBy("order_num")->values();
        return view("admin.config.gateways", ["gateways" => $gateways]);
    }
    public function registrars() { return view('admin.config.registrars'); }

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
        $v = $request->validate(['name' => 'required', 'email' => 'nullable|email']);
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
            'ips' => BannedIp::all(),
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
            'emails' => BannedEmail::all(),
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
        $v = $request->validate(["title" => "required", "announcement" => "required"]); $v["announcement"] = $v["announcement"] ?: ($request->body ?? "");
        Announcement::create($v);
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
            'categories' => KbCategory::with('articles')->get(),
        ]);
    }

    public function storeKbArticle(Request $request)
    {
        $v = $request->validate(['category_id' => 'required|exists:kb_categories,id', 'title' => 'required', 'article' => 'required']);
        KbArticle::create($v);
        return back()->with('success', __('messages.success.article_created'));
    }

    // ===== NETWORK ISSUES =====

    public function networkIssues()
    {
        return view('admin.config.network-issues', [
            'issues' => NetworkIssue::orderBy('id', 'desc')->get(),
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
            'items' => BillableItem::with('client')->orderBy('id', 'desc')->paginate(25),
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
        TodoItem::create($request->validate(['title' => 'required', 'due_date' => 'nullable|date']));
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

    public function systemDatabase() { return view('admin.config.system-database'); }
    public function systemPhpInfo() { return view('admin.config.system-phpinfo'); }

    // === Missing CRUD Methods ===

    // Ticket Departments
    public function updateTicketDepartment(Request $request, TicketDepartment $department) {
        $v = $request->validate([
            'name'=>'required','description'=>'nullable|string','email'=>'nullable|email',
            'clients_only'=>'boolean','hidden'=>'boolean','sort_order'=>'nullable|integer','feedback_request'=>'boolean',
            'import_protocol'=>'nullable|in:imap,pop3','import_host'=>'nullable|string|max:255',
            'import_port'=>'nullable|integer|min:1|max:65535','import_encryption'=>'nullable|in:ssl,tls,none',
            'import_username'=>'nullable|string|max:255','import_password'=>'nullable|string|max:255',
            'import_folder'=>'nullable|string|max:255',
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
    public function destroyTicketDepartment(TicketDepartment $department) {
        $department->delete();
        return back()->with('success', __('messages.success.department_deleted'));
    }

    // Ticket Statuses
    public function storeTicketStatus(Request $request) {
        TicketStatus::create($request->validate(['title'=>'required','color'=>'nullable|string','sort_order'=>'nullable|integer','show_active'=>'boolean','show_awaiting'=>'boolean','auto_close'=>'boolean']));
        return back()->with('success', __('messages.success.ticket_status_created'));
    }
    public function updateTicketStatus(Request $request, TicketStatus $status) {
        $status->update($request->validate(['title'=>'required','color'=>'nullable|string','sort_order'=>'nullable|integer','show_active'=>'boolean','show_awaiting'=>'boolean','auto_close'=>'boolean']));
        return back()->with('success', __('messages.success.ticket_status_updated'));
    }
    public function destroyTicketStatus(TicketStatus $status) {
        $status->delete();
        return back()->with('success', __('messages.success.ticket_status_deleted'));
    }

    // Email Templates
    public function updateEmailTemplate(Request $request, EmailTemplate $template) {
        $template->update($request->validate(['name'=>'nullable|string','subject'=>'nullable|string','message'=>'nullable|string','from_name'=>'nullable|string','from_email'=>'nullable|email','disabled'=>'boolean']));
        return back()->with('success', __('messages.success.template_updated'));
    }

    // Servers
    public function updateServer(Request $request, Server $server) {
        $server->update($request->validate(['name'=>'required','hostname'=>'required','ip_address'=>'nullable','port'=>'nullable|integer','type'=>'nullable|string','username'=>'nullable|string','password'=>'nullable|string','max_accounts'=>'nullable|integer','active'=>'boolean','nameserver1'=>'nullable','nameserver2'=>'nullable']));
        return back()->with('success', __('messages.success.server_updated'));
    }
    public function destroyServer(Server $server) {
        $server->delete();
        return back()->with('success', __('messages.success.server_deleted'));
    }
    public function testServerConnection(Server $server) {
        $host = $server->hostname ?? $server->ip_address;
        $port = $server->port ?? match($server->type) { "panelica" => 8443, "cpanel" => 2087, "plesk" => 8443, "directadmin" => 2222, default => 22 };
        if (empty($host)) {
            return back()->with("error", __("admin.messages.no_hostname"));
        }
        $start = microtime(true);
        $conn = @fsockopen($host, $port, $errno, $errstr, 5);
        $elapsed = round((microtime(true) - $start) * 1000);
        if ($conn) {
            fclose($conn);
            return back()->with("success", __("admin.messages.connection_success", ["host" => $host, "port" => $port, "elapsed" => $elapsed, "module" => $server->type ?? "custom"]));
        }
        return back()->with("error", __("admin.messages.connection_failed", ["host" => $host, "port" => $port, "error" => $errstr, "errno" => $errno, "elapsed" => $elapsed]));
    }

    // Server Groups
    public function serverGroups() {
        return view('admin.config.server-groups', ['serverGroups' => ServerGroup::withCount('servers')->get()]);
    }
    public function storeServerGroup(Request $request) {
        ServerGroup::create($request->validate(['name'=>'required','fill_type'=>'required|in:fill,round_robin']));
        return back()->with('success', __('messages.success.server_group_created'));
    }
    public function updateServerGroup(Request $request, ServerGroup $serverGroup) {
        $serverGroup->update($request->validate(['name'=>'required','fill_type'=>'required|in:fill,round_robin']));
        return back()->with('success', __('messages.success.server_group_updated'));
    }
    public function destroyServerGroup(ServerGroup $serverGroup) {
        $serverGroup->delete();
        return back()->with('success', __('messages.success.server_group_deleted'));
    }

    // Announcements
    public function updateAnnouncement(Request $request, Announcement $announcement) {
        $v = $request->validate(['title'=>'required','published'=>'boolean']); $v['announcement'] = $request->body ?? $request->announcement ?? $announcement->announcement; $announcement->update($v);
        return back()->with('success', __('messages.success.announcement_updated'));
    }
    public function destroyAnnouncement(Announcement $announcement) {
        $announcement->delete();
        return back()->with('success', __('messages.success.announcement_deleted'));
    }

    // Knowledge Base
    public function storeKbCategory(Request $request) {
        KbCategory::create($request->validate(['name'=>'required','description'=>'nullable|string','sort_order'=>'nullable|integer']));
        return back()->with('success', __('messages.success.category_created'));
    }
    public function updateKbArticle(Request $request, KbArticle $article) {
        $article->update($request->validate(['category_id'=>'required|exists:kb_categories,id','title'=>'required','article'=>'required','hidden'=>'boolean']));
        return back()->with('success', __('messages.success.article_updated'));
    }
    public function destroyKbArticle(KbArticle $article) {
        $article->delete();
        return back()->with('success', __('messages.success.article_deleted'));
    }

    // Downloads
    public function storeDownloadCategory(Request $request) {
        DownloadCategory::create($request->validate(['name'=>'required']));
        return back()->with('success', __('messages.success.category_created'));
    }
    public function storeDownload(Request $request) {
        Download::create($request->validate(['category_id'=>'nullable','title'=>'required','description'=>'nullable|string','location'=>'required']));
        return back()->with('success', __('messages.success.download_created'));
    }
    public function destroyDownload(Download $download) {
        $download->delete();
        return back()->with('success', __('messages.success.download_deleted'));
    }

    // Network Issues
    public function storeNetworkIssue(Request $request) {
        NetworkIssue::create($request->validate(['title'=>'required','description'=>'nullable|string','type'=>'nullable|string','status'=>'required','affected'=>'nullable|string','start_date'=>'nullable|date','end_date'=>'nullable|date']));
        return back()->with('success', __('messages.success.network_issue_created'));
    }
    public function updateNetworkIssue(Request $request, NetworkIssue $issue) {
        $issue->update($request->validate(['title'=>'required','description'=>'nullable|string','type'=>'nullable|string','status'=>'required','affected'=>'nullable|string','start_date'=>'nullable|date','end_date'=>'nullable|date']));
        return back()->with('success', __('messages.success.network_issue_updated'));
    }
    public function destroyNetworkIssue(NetworkIssue $issue) {
        $issue->delete();
        return back()->with('success', __('messages.success.network_issue_deleted'));
    }

    // Banned IPs/Emails
    public function destroyBannedIp(BannedIp $bannedIp) {
        $bannedIp->delete();
        return back()->with('success', __('messages.success.ip_unbanned'));
    }
    public function storeBannedEmail(Request $request) {
        BannedEmail::create($request->validate(['domain'=>'required','type'=>'nullable|string','reason'=>'nullable|string']));
        return back()->with('success', __('messages.success.email_banned'));
    }
    public function destroyBannedEmail(BannedEmail $bannedEmail) {
        $bannedEmail->delete();
        return back()->with('success', __('messages.success.email_unbanned'));
    }

    // To-Do
    public function updateTodo(Request $request, TodoItem $todo) {
        $todo->update($request->validate(['title'=>'required','description'=>'nullable|string','status'=>'nullable|string','due_date'=>'nullable|date']));
        return back()->with('success', __('messages.success.todo_updated'));
    }
    public function destroyTodo(TodoItem $todo) {
        $todo->delete();
        return back()->with('success', __('messages.success.todo_deleted'));
    }

    // Billable Items
    public function storeBillableItem(Request $request) {
        BillableItem::create($request->validate(['client_id'=>'required|exists:clients,id','description'=>'required','amount'=>'required|numeric','due_date'=>'nullable|date']));
        return back()->with('success', __('messages.success.billable_item_created'));
    }
    public function destroyBillableItem(BillableItem $item) {
        $item->delete();
        return back()->with('success', __('messages.success.billable_item_deleted'));
    }

    // Domain Pricing
    public function updateTld(Request $request, DomainPricing $domainPricing) {
        $domainPricing->update($request->validate(['extension'=>'required','register_price'=>'nullable|numeric','transfer_price'=>'nullable|numeric','renew_price'=>'nullable|numeric']));
        return back()->with('success', __('messages.success.tld_updated'));
    }
    public function destroyTld(DomainPricing $domainPricing) {
        $domainPricing->delete();
        return back()->with('success', __('messages.success.tld_deleted'));
    }

    // Gateway/Registrar settings
    public function updateGatewaySettings(Request $request, string $gateway) {
        $settings = $request->input("settings", []);
        $json = $request->input("settings_json");
        if ($json && empty($settings)) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) $settings = $decoded;
        }
        foreach ($settings as $key => $value) {
            GatewaySettings::updateOrCreate(
                ["gateway" => $gateway, "setting" => $key],
                ["value" => $value ?? ""]
            );
        }
        return back()->with("success", __("admin.messages.gateway_updated"));
    }
    public function updateRegistrarSettings(Request $request, string $registrar) {
        return back()->with('success', __('messages.success.registrar_updated'));
    }


    // ===== AUTOMATION =====
    public function automation() {
        return view("admin.config.automation");
    }

    // ===== CLIENT GROUPS =====
    public function clientGroups() {
        return view("admin.config.client-groups", ["groups" => ClientGroup::withCount("clients")->get()]);
    }

    public function storeClientGroup(Request $request) {
        ClientGroup::create($request->validate(["name" => "required", "color" => "nullable|string|max:7", "discount_percent" => "nullable|numeric|min:0|max:100"]));
        return back()->with("success", __("admin.messages.client_group_created"));
    }

    public function updateClientGroup(Request $request, ClientGroup $group) {
        $group->update($request->validate(["name" => "required", "color" => "nullable|string|max:7", "discount_percent" => "nullable|numeric|min:0|max:100"]));
        return back()->with("success", __("admin.messages.client_group_updated"));
    }

    public function destroyClientGroup(ClientGroup $group) {
        $group->delete();
        return back()->with("success", __("admin.messages.client_group_deleted"));
    }

    // ===== API DOCS =====

    public function apiDocs()
    {
        return view('admin.api-docs');
    }

    // ===== SSL MODULES =====
    public function sslModules()
    {
        $registry = app(\App\Services\Module\ModuleRegistry::class);
        $modules = [];
        $settings = [];

        foreach ($registry->getSslModules() as $name) {
            $module = $registry->getSslModule($name);
            if ($module) {
                $modules[$name] = $module;
                $settings[$name] = \App\Models\SslModuleSettings::getForModule($name);
            }
        }

        return view('admin.config.ssl-modules', compact('modules', 'settings'));
    }

    public function updateSslModuleSettings(Request $request, string $module)
    {
        $settings = $request->input('settings', []);

        foreach ($settings as $key => $value) {
            \App\Models\SslModuleSettings::setSetting($module, $key, $value);
        }

        return back()->with('success', __('messages.success.ssl_module_updated'));
    }

    public function testSslConnection(string $module)
    {
        $registry = app(\App\Services\Module\ModuleRegistry::class);
        $sslModule = $registry->getSslModule($module);

        if (!$sslModule) {
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
        $groups = ConfigOptionGroup::with("options.subs")->get();
        return view("admin.config.config-options", compact("groups"));
    }

    public function storeConfigOptionGroup(Request $request)
    {
        $validated = $request->validate([
            "name" => "required|string|max:255",
            "description" => "nullable|string",
        ]);
        ConfigOptionGroup::create($validated);
        return back()->with("success", __("admin.messages.config_option_group_created"));
    }

    public function updateConfigOptionGroup(Request $request, $id)
    {
        $group = ConfigOptionGroup::findOrFail($id);
        $validated = $request->validate([
            "name" => "required|string|max:255",
            "description" => "nullable|string",
        ]);
        $group->update($validated);
        return back()->with("success", __("admin.messages.config_option_group_updated"));
    }

    public function deleteConfigOptionGroup($id)
    {
        ConfigOptionGroup::findOrFail($id)->delete();
        return back()->with("success", __("admin.messages.config_option_group_deleted"));
    }

    public function storeConfigOption(Request $request)
    {
        $validated = $request->validate([
            "group_id" => "required|exists:config_option_groups,id",
            "option_name" => "required|string|max:255",
            "option_type" => "required|in:dropdown,radio,checkbox,quantity,text",
            "sort_order" => "nullable|integer",
        ]);
        ConfigOption::create($validated);
        return back()->with("success", __("admin.messages.config_option_created"));
    }

    public function deleteConfigOption($id)
    {
        ConfigOption::findOrFail($id)->delete();
        return back()->with("success", __("admin.messages.config_option_deleted"));
    }

    public function storeConfigOptionSub(Request $request)
    {
        $validated = $request->validate([
            "config_id" => "required|exists:config_options,id",
            "option_name" => "required|string|max:255",
            "sort_order" => "nullable|integer",
        ]);
        ConfigOptionSub::create($validated);
        return back()->with("success", __("admin.messages.sub_option_created"));
    }

    public function deleteConfigOptionSub($id)
    {
        ConfigOptionSub::findOrFail($id)->delete();
        return back()->with("success", __("admin.messages.sub_option_deleted"));
    }

    // ===== TICKET ESCALATION =====

    public function ticketEscalation()
    {
        $rules = TicketEscalation::all();
        $departments = TicketDepartment::all();
        $admins = Admin::where("is_disabled", false)->get();
        return view("admin.config.ticket-escalation", compact("rules", "departments", "admins"));
    }

    public function storeTicketEscalation(Request $request)
    {
        $validated = $request->validate([
            "name" => "required|string|max:255",
            "departments" => "nullable|json",
            "statuses" => "nullable|json",
            "priorities" => "nullable|json",
            "time_elapsed" => "required|integer|min:1",
            "new_department_id" => "nullable|exists:ticket_departments,id",
            "new_priority" => "nullable|string",
            "flag_to" => "nullable|exists:admins,id",
            "notify" => "boolean",
            "add_reply" => "nullable|string",
        ]);
        $validated["notify"] = $request->boolean("notify");
        TicketEscalation::create($validated);
        return back()->with("success", __("admin.messages.escalation_rule_created"));
    }

    public function updateTicketEscalation(Request $request, $id)
    {
        $rule = TicketEscalation::findOrFail($id);
        $validated = $request->validate([
            "name" => "required|string|max:255",
            "departments" => "nullable|json",
            "statuses" => "nullable|json",
            "priorities" => "nullable|json",
            "time_elapsed" => "required|integer|min:1",
            "new_department_id" => "nullable|exists:ticket_departments,id",
            "new_priority" => "nullable|string",
            "flag_to" => "nullable|exists:admins,id",
            "notify" => "boolean",
            "add_reply" => "nullable|string",
        ]);
        $validated["notify"] = $request->boolean("notify");
        $rule->update($validated);
        return back()->with("success", __("admin.messages.escalation_rule_updated"));
    }

    public function deleteTicketEscalation($id)
    {
        TicketEscalation::findOrFail($id)->delete();
        return back()->with("success", __("admin.messages.escalation_rule_deleted"));
    }


    // ===== NOTIFICATION CHANNELS (Phase 7) =====

    public function notifications()
    {
        $providers = \App\Models\NotificationProvider::with("rules")->get();
        $eventTypes = [
            "client.created", "order.placed", "invoice.created", "invoice.paid",
            "ticket.opened", "ticket.replied", "service.activated", "service.suspended", "service.terminated",
        ];
        return view("admin.config.notifications", compact("providers", "eventTypes"));
    }

    public function storeNotificationProvider(Request $request)
    {
        $v = $request->validate([
            "name" => "required|string|max:255",
            "type" => "required|in:email,slack,webhook",
            "settings" => "nullable|array",
            "active" => "boolean",
        ]);
        $v["active"] = $request->boolean("active");
        $v["settings"] = $request->input("settings", []);
        \App\Models\NotificationProvider::create($v);
        return back()->with("success", __("admin.messages.notification_provider_created"));
    }

    public function updateNotificationProvider(Request $request, $id)
    {
        $provider = \App\Models\NotificationProvider::findOrFail($id);
        $v = $request->validate([
            "name" => "required|string|max:255",
            "type" => "required|in:email,slack,webhook",
            "settings" => "nullable|array",
            "active" => "boolean",
        ]);
        $v["active"] = $request->boolean("active");
        $v["settings"] = $request->input("settings", []);
        $provider->update($v);
        return back()->with("success", __("admin.messages.notification_provider_updated"));
    }

    public function destroyNotificationProvider($id)
    {
        $provider = \App\Models\NotificationProvider::findOrFail($id);
        $provider->rules()->delete();
        $provider->delete();
        return back()->with("success", __("admin.messages.notification_provider_deleted"));
    }

    public function storeNotificationRule(Request $request)
    {
        $v = $request->validate([
            "event" => "required|string|max:255",
            "provider_id" => "required|exists:notification_providers,id",
            "conditions" => "nullable|array",
            "active" => "boolean",
        ]);
        $v["active"] = $request->boolean("active");
        $v["conditions"] = $request->input("conditions", []);
        \App\Models\NotificationRule::create($v);
        return back()->with("success", __("admin.messages.notification_rule_created"));
    }

    public function destroyNotificationRule($id)
    {
        \App\Models\NotificationRule::findOrFail($id)->delete();
        return back()->with("success", __("admin.messages.notification_rule_deleted"));
    }

    // ===== TICKET SPAM FILTER (Phase 12) =====

    public function ticketSpam()
    {
        $emailFilters = \App\Models\TicketSpamFilter::where("type", "email")->pluck("content")->implode("\n");
        $keywordFilters = \App\Models\TicketSpamFilter::where("type", "keyword")->pluck("content")->implode("\n");
        return view("admin.config.ticket-spam", [
            "bannedEmails" => $emailFilters,
            "bannedKeywords" => $keywordFilters,
            "maxPerHour" => Setting::get("TicketSpamMaxPerHour", 5),
            "filters" => \App\Models\TicketSpamFilter::orderBy("id", "desc")->get(),
        ]);
    }

    public function updateTicketSpam(Request $request)
    {
        // Sync email patterns
        $emails = array_filter(array_map("trim", explode("\n", $request->input("banned_emails", ""))));
        \App\Models\TicketSpamFilter::where("type", "email")->delete();
        foreach ($emails as $pattern) {
            if ($pattern) {
                \App\Models\TicketSpamFilter::create(["type" => "email", "content" => $pattern]);
            }
        }

        // Sync keyword patterns
        $keywords = array_filter(array_map("trim", explode("\n", $request->input("banned_keywords", ""))));
        \App\Models\TicketSpamFilter::where("type", "keyword")->delete();
        foreach ($keywords as $keyword) {
            if ($keyword) {
                \App\Models\TicketSpamFilter::create(["type" => "keyword", "content" => $keyword]);
            }
        }

        Setting::set("TicketSpamMaxPerHour", $request->input("max_per_hour", 5), "tickets");
        return back()->with("success", __("admin.messages.spam_filter_updated"));
    }

    public function storeTicketSpamFilter(Request $request)
    {
        $v = $request->validate([
            "type" => "required|in:email,keyword",
            "content" => "required|string|max:255",
        ]);
        \App\Models\TicketSpamFilter::create($v);
        return back()->with("success", __("admin.messages.spam_filter_added"));
    }

    public function destroyTicketSpamFilter($id)
    {
        \App\Models\TicketSpamFilter::findOrFail($id)->delete();
        return back()->with("success", __("admin.messages.spam_filter_removed"));
    }

    // ===== PRODUCT ADDONS (Phase 14) =====

    public function addons()
    {
        $addons = \App\Models\ProductAddon::orderBy("sort_order")->get();
        $products = \App\Models\Product::orderBy("name")->get();
        return view("admin.config.addons", compact("addons", "products"));
    }

    public function storeAddon(Request $request)
    {
        $v = $request->validate([
            "name" => "required|string|max:255",
            "description" => "nullable|string",
            "sort_order" => "nullable|integer",
            "hidden" => "boolean",
            "tax" => "boolean",
        ]);
        $v["hidden"] = $request->boolean("hidden");
        $v["tax"] = $request->boolean("tax");
        $v["sort_order"] = $v["sort_order"] ?? 0;

        // Store applicable products as comma-separated IDs
        $packages = $request->input("packages", []);
        $v["packages"] = !empty($packages) ? implode(",", $packages) : null;

        \App\Models\ProductAddon::create($v);
        return back()->with("success", __("admin.messages.addon_created"));
    }

    public function updateAddon(Request $request, $id)
    {
        $addon = \App\Models\ProductAddon::findOrFail($id);
        $v = $request->validate([
            "name" => "required|string|max:255",
            "description" => "nullable|string",
            "sort_order" => "nullable|integer",
            "hidden" => "boolean",
            "retired" => "boolean",
            "tax" => "boolean",
        ]);
        $v["hidden"] = $request->boolean("hidden");
        $v["retired"] = $request->boolean("retired");
        $v["tax"] = $request->boolean("tax");
        $addon->update($v);
        return back()->with("success", __("admin.messages.addon_updated"));
    }

    public function destroyAddon($id)
    {
        \App\Models\ProductAddon::findOrFail($id)->delete();
        return back()->with("success", __("admin.messages.addon_deleted"));
    }

    // ===== PRODUCT BUNDLES (Phase 15) =====

    public function bundles()
    {
        $bundles = \App\Models\ProductBundle::with(["items.product"])->orderBy("name")->get();
        $products = \App\Models\Product::with("group")->orderBy("name")->get();
        return view("admin.config.bundles", compact("bundles", "products"));
    }

    public function storeBundle(Request $request)
    {
        $v = $request->validate([
            "name" => "required|string|max:255",
            "description" => "nullable|string",
            "discount_type" => "required|in:percentage,fixed",
            "discount_value" => "required|numeric|min:0",
            "is_active" => "boolean",
            "product_ids" => "required|array|min:2",
            "product_ids.*" => "exists:products,id",
        ]);

        $bundle = \App\Models\ProductBundle::create([
            "name" => $v["name"],
            "description" => $v["description"] ?? null,
            "discount_type" => $v["discount_type"],
            "discount_value" => $v["discount_value"],
            "is_active" => $request->boolean("is_active"),
        ]);

        foreach ($v["product_ids"] as $productId) {
            $bundle->items()->create([
                "item_type" => "product",
                "item_id" => $productId,
                "qty" => 1,
            ]);
        }

        return back()->with("success", __("admin.messages.bundle_created"));
    }

    public function destroyBundle($id)
    {
        $bundle = \App\Models\ProductBundle::findOrFail($id);
        $bundle->items()->delete();
        $bundle->delete();
        return back()->with("success", __("admin.messages.bundle_deleted"));
    }

}
