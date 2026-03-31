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
use App\Models\Download;
use App\Models\DownloadCategory;
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
        return back()->with('success', 'Admin created successfully.');
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
        return back()->with('success', 'Admin updated successfully.');
    }

    public function destroyAdmin(Admin $admin)
    {
        if ($admin->id === auth('admin')->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        $admin->delete();
        return back()->with('success', 'Admin deleted successfully.');
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
        return back()->with('success', 'Role created successfully.');
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
        return back()->with('success', 'Role updated successfully.');
    }

    public function destroyRole(AdminRole $role)
    {
        if ($role->admins()->count() > 0) {
            return back()->with('error', 'Cannot delete role: it still has admins assigned.');
        }
        $role->delete();
        return back()->with('success', 'Role deleted successfully.');
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
        ApiCredential::create([
            'admin_id'    => auth('admin')->id(),
            'identifier'  => Str::random(32),
            'secret'      => Str::random(64),
            'description' => $request->description,
            'active'      => true,
        ]);
        return back()->with('success', 'API credential generated.');
    }

    public function destroyApiCredential(ApiCredential $credential)
    {
        $credential->delete();
        return back()->with('success', 'API credential revoked.');
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
        return back()->with('success', 'Currency added.');
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
        return back()->with('success', 'Currency updated.');
    }

    public function destroyCurrency(Currency $currency)
    {
        if ($currency->is_default) {
            return back()->with('error', 'Cannot delete the default currency.');
        }
        $currency->delete();
        return back()->with('success', 'Currency deleted.');
    }

    public function setDefaultCurrency(Currency $currency)
    {
        Currency::query()->update(['is_default' => false]);
        $currency->update(['is_default' => true]);
        return back()->with('success', 'Default currency updated.');
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
        $v = $request->validate([
            'name'     => 'required',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'country'  => 'nullable|string|max:2',
            'state'    => 'nullable|string',
            'level'    => 'nullable|integer|min:1|max:2',
        ]);
        TaxRule::create($v);
        return back()->with('success', 'Tax rule added.');
    }

    public function updateTax(Request $request, TaxRule $taxRule)
    {
        $v = $request->validate([
            'name'     => 'required',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'country'  => 'nullable|string|max:2',
            'state'    => 'nullable|string',
            'level'    => 'nullable|integer|min:1|max:2',
        ]);
        $taxRule->update($v);
        return back()->with('success', 'Tax rule updated.');
    }

    public function destroyTax(TaxRule $taxRule)
    {
        $taxRule->delete();
        return back()->with('success', 'Tax rule deleted.');
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
        return back()->with('success', 'Promotion created.');
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
        return back()->with('success', 'Promotion updated.');
    }

    public function destroyPromotion(Promotion $promotion)
    {
        $promotion->delete();
        return back()->with('success', 'Promotion deleted.');
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
        return back()->with('success', 'Server added.');
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
        return back()->with('success', 'TLD added.');
    }

    // ===== PAYMENT GATEWAYS / REGISTRARS =====

    public function gateways() { return view('admin.config.gateways'); }
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
        return back()->with('success', 'Department created.');
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
        return back()->with('success', 'IP banned.');
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
        $v = $request->validate(['title' => 'required', 'announcement' => 'required']);
        Announcement::create($v);
        return back()->with('success', 'Announcement published.');
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
        return back()->with('success', 'Article created.');
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
        return back()->with('success', 'To-do added.');
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
}
