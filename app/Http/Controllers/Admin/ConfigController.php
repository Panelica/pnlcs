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
    // Staff
    public function admins() { return view('admin.config.admins', ['admins' => Admin::with('role')->get(), 'roles' => AdminRole::all()]); }
    public function storeAdmin(Request $request) {
        $v = $request->validate(['username'=>'required|unique:admins','email'=>'required|email|unique:admins','password'=>'required|min:6','first_name'=>'required','last_name'=>'required','role_id'=>'required|exists:admin_roles,id']);
        $v['password'] = Hash::make($v['password']);
        Admin::create($v);
        return back()->with('success','Admin created.');
    }

    // Admin Roles
    public function adminRoles() { return view('admin.config.admin-roles', ['roles' => AdminRole::withCount('admins')->get()]); }

    // API Credentials
    public function apiCredentials() { return view('admin.config.api-credentials', ['credentials' => ApiCredential::with('admin')->get()]); }
    public function storeApiCredential(Request $request) {
        ApiCredential::create(['admin_id'=>auth('admin')->id(),'identifier'=>Str::random(32),'secret'=>Str::random(64),'description'=>$request->description,'active'=>true]);
        return back()->with('success','API credential created.');
    }

    // Currencies
    public function currencies() { return view('admin.config.currencies', ['currencies' => Currency::all()]); }
    public function storeCurrency(Request $request) {
        $v = $request->validate(['code'=>'required|string|max:3|unique:currencies','prefix'=>'nullable|string|max:10','suffix'=>'nullable|string|max:10','rate'=>'required|numeric']);
        Currency::create($v);
        return back()->with('success','Currency added.');
    }

    // Tax
    public function tax() { return view('admin.config.tax', ['rules' => TaxRule::all()]); }
    public function storeTax(Request $request) {
        $v = $request->validate(['name'=>'required','tax_rate'=>'required|numeric','country'=>'nullable|string|max:2','state'=>'nullable|string']);
        TaxRule::create($v);
        return back()->with('success','Tax rule added.');
    }

    // Servers
    public function servers() { return view('admin.config.servers', ['servers' => Server::all(), 'groups' => ServerGroup::all()]); }
    public function storeServer(Request $request) {
        $v = $request->validate(['name'=>'required','hostname'=>'required','type'=>'nullable|string']);
        Server::create($v);
        return back()->with('success','Server added.');
    }

    // Domain Pricing
    public function domainPricing() { return view('admin.config.domain-pricing', ['tlds' => DomainPricing::orderBy('sort_order')->get()]); }
    public function storeTld(Request $request) {
        $v = $request->validate(['extension'=>'required|string|unique:domain_pricing']);
        DomainPricing::create($v);
        return back()->with('success','TLD added.');
    }

    // Payment Gateways
    public function gateways() { return view('admin.config.gateways'); }

    // Registrars
    public function registrars() { return view('admin.config.registrars'); }

    // Email Templates
    public function emailTemplates() { return view('admin.config.email-templates', ['templates' => EmailTemplate::orderBy('type')->get()]); }

    // Ticket Departments
    public function ticketDepartments() { return view('admin.config.ticket-departments', ['departments' => TicketDepartment::orderBy('sort_order')->get()]); }
    public function storeTicketDepartment(Request $request) {
        $v = $request->validate(['name'=>'required','email'=>'nullable|email']);
        TicketDepartment::create($v);
        return back()->with('success','Department created.');
    }

    // Ticket Statuses
    public function ticketStatuses() { return view('admin.config.ticket-statuses', ['statuses' => TicketStatus::orderBy('sort_order')->get()]); }

    // Promotions
    public function promotions() { return view('admin.config.promotions', ['promotions' => Promotion::orderBy('id','desc')->get()]); }
    public function storePromotion(Request $request) {
        $v = $request->validate(['code'=>'required|unique:promotions','type'=>'required','value'=>'required|numeric']);
        Promotion::create($v);
        return back()->with('success','Promotion created.');
    }

    // Banned IPs
    public function bannedIps() { return view('admin.config.banned-ips', ['ips' => BannedIp::all()]); }
    public function storeBannedIp(Request $request) {
        BannedIp::create($request->validate(['ip'=>'required','reason'=>'nullable|string']));
        return back()->with('success','IP banned.');
    }

    // Banned Emails
    public function bannedEmails() { return view('admin.config.banned-emails', ['emails' => BannedEmail::all()]); }

    // Announcements
    public function announcements() { return view('admin.config.announcements', ['announcements' => Announcement::orderBy('id','desc')->get()]); }
    public function storeAnnouncement(Request $request) {
        $v = $request->validate(['title'=>'required','announcement'=>'required']);
        Announcement::create($v);
        return back()->with('success','Announcement published.');
    }

    // Downloads
    public function downloads() { return view('admin.config.downloads', ['categories' => DownloadCategory::with('downloads')->get()]); }

    // Knowledge Base
    public function knowledgeBase() { return view('admin.config.knowledge-base', ['categories' => KbCategory::with('articles')->get()]); }
    public function storeKbArticle(Request $request) {
        $v = $request->validate(['category_id'=>'required|exists:kb_categories,id','title'=>'required','article'=>'required']);
        KbArticle::create($v);
        return back()->with('success','Article created.');
    }

    // Network Issues
    public function networkIssues() { return view('admin.config.network-issues', ['issues' => NetworkIssue::orderBy('id','desc')->get()]); }

    // Affiliates
    public function affiliates() { return view('admin.config.affiliates', ['affiliates' => Affiliate::with('client')->get()]); }

    // Quotes
    public function quotes() { return view('admin.config.quotes', ['quotes' => Quote::with('client')->orderBy('id','desc')->paginate(25)]); }

    // Billable Items
    public function billableItems() { return view('admin.config.billable-items', ['items' => BillableItem::with('client')->orderBy('id','desc')->paginate(25)]); }

    // Transactions
    public function transactions() { return view('admin.config.transactions', ['transactions' => Transaction::with('client')->orderBy('id','desc')->paginate(25)]); }

    // To-Do
    public function todoList() { return view('admin.config.todo', ['items' => TodoItem::orderBy('id','desc')->get()]); }
    public function storeTodo(Request $request) {
        TodoItem::create($request->validate(['title'=>'required','due_date'=>'nullable|date']));
        return back()->with('success','To-do added.');
    }

    // Activity Log
    public function activityLog() { return view('admin.config.activity-log', ['logs' => ActivityLog::orderBy('id','desc')->paginate(50)]); }

    // System
    public function systemDatabase() { return view('admin.config.system-database'); }
    public function systemPhpInfo() { return view('admin.config.system-phpinfo'); }
}
