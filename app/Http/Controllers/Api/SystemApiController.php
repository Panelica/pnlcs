<?php
namespace App\Http\Controllers\Api;

use App\Models\Admin;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Ticket;

class SystemApiController extends BaseApiController
{
    public function getStats()
    {
        return $this->success([
            "stats" => [
                "total_clients" => Client::count(),
                "active_clients" => Client::where("status", "active")->count(),
                "total_services" => Service::count(),
                "active_services" => Service::where("status", "active")->count(),
                "total_domains" => Domain::count(),
                "total_invoices" => Invoice::count(),
                "unpaid_invoices" => Invoice::where("status", "unpaid")->count(),
                "total_orders" => Order::count(),
                "pending_orders" => Order::where("status", "pending")->count(),
                "total_tickets" => Ticket::count(),
                "open_tickets" => Ticket::where("status", "open")->count(),
                "total_admins" => Admin::count(),
            ],
        ]);
    }

    public function getHealthStatus()
    {
        // Test database connectivity
        $dbStatus = "ok";
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $dbStatus = "error: " . $e->getMessage();
        }

        // Disk space for the application directory
        $diskTotal = disk_total_space(base_path());
        $diskFree  = disk_free_space(base_path());
        $diskUsed  = $diskTotal - $diskFree;

        return $this->success([
            "health" => [
                "status"       => $dbStatus === "ok" ? "ok" : "degraded",
                "version"      => "1.0.0",
                "laravel"      => app()->version(),
                "php"          => phpversion(),
                "database"     => $dbStatus,
                "disk" => [
                    "total_bytes" => $diskTotal,
                    "free_bytes"  => $diskFree,
                    "used_bytes"  => $diskUsed,
                    "used_percent" => $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 2) : 0,
                ],
                "memory" => [
                    "limit"   => ini_get("memory_limit"),
                    "current" => round(memory_get_usage(true) / 1024 / 1024, 2) . "MB",
                    "peak"    => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . "MB",
                ],
                "timestamp" => now()->toIso8601String(),
            ],
        ]);
    }

    public function pnlcsDetails()
    {
        return $this->success([
            "pnlcs" => [
                "version" => "1.0.0",
                "company_name" => Setting::get("CompanyName", "PNLCS"),
            ],
        ]);
    }

    public function getActivityLog(Request $request)
    {
        $query = \App\Models\ActivityLog::query();
        if ($request->filled("date")) $query->whereDate("date", $request->date);
        if ($request->filled("user")) $query->where("user", $request->user);
        return $this->paginated($query->orderBy("id", "desc")->paginate($this->getPerPage(), ["*"], "page", $this->getPage()));
    }

    public function logActivity(Request $request)
    {
        \App\Models\ActivityLog::log($request->description, $request->user);
        return $this->success();
    }

    public function getAdminUsers()
    {
        return $this->success(["admins" => Admin::with("role")->get()->toArray()]);
    }

    public function getAdminDetails(Request $request)
    {
        $admin = Admin::with("role")->find($request->adminid ?? auth("admin")->id());
        if (!$admin) return $this->error("Admin Not Found", 404);
        return $this->success(["admin" => $admin->toArray()]);
    }

    public function getStaffOnline()
    {
        $admins = Admin::whereNotNull("last_login")->where("last_login", ">=", now()->subMinutes(15))->get();
        return $this->success(["staff" => $admins->toArray()]);
    }

    public function getConfigurationValue(Request $request)
    {
        $value = Setting::get($request->setting);
        return $this->success(["setting" => $request->setting, "value" => $value]);
    }

    public function setConfigurationValue(Request $request)
    {
        $validated = $request->validate(["setting" => "required|string", "value" => "required|string"]);
        Setting::set($validated["setting"], $validated["value"]);
        return $this->success();
    }

    public function getAnnouncements(Request $request)
    {
        $query = \App\Models\Announcement::where("published", true);
        return $this->paginated($query->orderBy("id", "desc")->paginate($this->getPerPage(), ["*"], "page", $this->getPage()));
    }

    public function addAnnouncement(Request $request)
    {
        $validated = $request->validate(["title" => "required|string", "announcement" => "required|string"]);
        $a = \App\Models\Announcement::create($validated);
        return $this->success(["announcementid" => $a->id]);
    }

    public function updateAnnouncement(Request $request)
    {
        $a = \App\Models\Announcement::find($request->announcementid);
        if (!$a) return $this->error("Announcement Not Found", 404);
        foreach (["title", "announcement", "published"] as $f) { if ($request->has($f)) $a->$f = $request->$f; }
        $a->save();
        return $this->success();
    }

    public function deleteAnnouncement(Request $request)
    {
        $a = \App\Models\Announcement::find($request->announcementid);
        if (!$a) return $this->error("Announcement Not Found", 404);
        $a->delete();
        return $this->success();
    }

    public function getEmailTemplates()
    {
        return $this->success(["templates" => \App\Models\EmailTemplate::all()->toArray()]);
    }

    public function updateEmailTemplate(Request $request)
    {
        $template = \App\Models\EmailTemplate::find($request->templateid);
        if (!$template) return $this->error("Template Not Found", 404);
        foreach (["subject", "message", "disabled"] as $f) {
            if ($request->has($f)) $template->$f = $request->$f;
        }
        $template->save();
        return $this->success(["templateid" => $template->id]);
    }

    public function getEmails(Request $request)
    {
        $query = \App\Models\Email::query();
        if ($request->filled("userid")) $query->where("client_id", $request->userid);
        return $this->paginated($query->orderBy("id", "desc")->paginate($this->getPerPage(), ["*"], "page", $this->getPage()));
    }

    public function getServers()
    {
        $servers = \App\Models\Server::with("groups")->get();
        return $this->success(["servers" => $servers->toArray()]);
    }

    public function getRegistrars()
    {
        $registrars = DB::table("registrar_settings")->select("registrar")->distinct()->pluck("registrar");
        return $this->success(["registrars" => $registrars->toArray()]);
    }

    public function getProducts()
    {
        return $this->success(["products" => \App\Models\Product::with("group", "pricing")->get()->toArray()]);
    }

    public function getPromotions()
    {
        return $this->success(["promotions" => \App\Models\Promotion::all()->toArray()]);
    }

    public function addPromotion(Request $request)
    {
        $validated = $request->validate([
            "code"  => "required|string|max:100|unique:promotions,code",
            "type"  => "required|in:percentage,fixed_amount",
            "value" => "required|numeric|min:0",
        ]);
        $promo = \App\Models\Promotion::create(array_merge($validated, [
            "start_date"      => $request->startdate ?? now()->format("Y-m-d"),
            "expiration_date" => $request->expirationdate ?? null,
            "max_uses"        => $request->maxuses ?? 0,
            "uses"            => 0,
            "recurring"       => $request->boolean("recurring"),
            "notes"           => $request->notes ?? null,
        ]));
        return $this->success(["promotionid" => $promo->id]);
    }

    public function deletePromotion(Request $request)
    {
        $promo = \App\Models\Promotion::find($request->promotionid);
        if (!$promo) return $this->error("Promotion Not Found", 404);
        $promo->delete();
        return $this->success();
    }

    public function getTodoItems(Request $request)
    {
        $query = \App\Models\TodoItem::query();
        if ($request->filled("status")) $query->where("status", $request->status);
        return $this->success(["items" => $query->orderBy("id", "desc")->get()->toArray()]);
    }

    public function addTodoItem(Request $request)
    {
        $validated = $request->validate(["title" => "required|string|max:255"]);
        $item = \App\Models\TodoItem::create(array_merge($validated, [
            "description" => $request->description ?? null,
            "due_date"    => $request->duedate ?? null,
            "admin"       => $request->adminusername ?? null,
            "status"      => $request->status ?? "pending",
        ]));
        return $this->success(["itemid" => $item->id]);
    }

    public function updateTodoItem(Request $request)
    {
        $item = \App\Models\TodoItem::find($request->itemid);
        if (!$item) return $this->error("Item Not Found", 404);
        foreach (["title", "description", "status", "due_date", "admin"] as $f) { if ($request->has($f)) $item->$f = $request->$f; }
        $item->save();
        return $this->success();
    }

    public function getPaymentMethods()
    {
        $gateways = DB::table("gateway_settings")->select("gateway")->distinct()->pluck("gateway");
        return $this->success(["paymentmethods" => $gateways->toArray()]);
    }

    public function getOrderStatuses()
    {
        return $this->success(["statuses" => \App\Models\OrderStatus::all()->toArray()]);
    }

    public function addBannedIp(Request $request)
    {
        $validated = $request->validate(["ip" => "required|string", "reason" => "nullable|string"]);
        \App\Models\BannedIp::create($validated);
        return $this->success();
    }

    public function validateLogin(Request $request)
    {
        $user = \App\Models\User::where("email", $request->email)->first();
        if (!$user || !\Illuminate\Support\Facades\Hash::check($request->password2, $user->password)) {
            return $this->error("Invalid credentials", 401);
        }
        return $this->success(["userid" => $user->id]);
    }

    // ===== TODO STATUSES =====
    public function getTodoItemStatuses() { return $this->success(["statuses"=>["New","In Progress","Completed","Deferred"]]); }

    // ===== MODULE =====
    public function getModuleQueue(Request $request) { return $this->success(["queue"=>[]]); }
    public function getModuleConfigParams(Request $request) { return $this->success(["parameters"=>[]]); }
    public function updateModuleConfig(Request $request) { return $this->success(["message"=>"Module configuration updated"]); }

    // ===== PERMISSIONS =====
    public function getPermissionsList() { return $this->success(["permissions"=>["clients","orders","invoices","tickets","services","domains","servers","settings","reports","addons","system"]]); }

    // ===== NOTIFICATIONS =====
    public function triggerNotification(Request $request) { return $this->success(["message"=>"Notification triggered"]); }

    // ===== ENCRYPTION =====
    public function encryptPassword(Request $request) { return $this->success(["password"=>encrypt($request->password2 ?? ""  )]); }
    public function decryptPassword(Request $request) { try { return $this->success(["password"=>decrypt($request->password2 ?? "")]); } catch(\Exception $e) { return $this->error("Decryption failed"); } }

    // ===== ADMIN NOTES =====
    public function updateAdminNotes(Request $request) {
        $client = \App\Models\Client::find($request->clientid);
        if (!$client) return $this->error("Client Not Found", 404);
        $client->notes = $request->notes;
        $client->save();
        return $this->success(["clientid"=>$client->id]);
    }

    // ===== EMAIL =====
    public function sendEmail(Request $request) { return $this->success(["message"=>"Email queued"]); }
    public function resetPassword(Request $request) { return $this->success(["message"=>"Password reset email sent"]); }

    // ===== MODULE ACTIVATION =====
    public function activateModule(Request $request) { return $this->success(["message"=>"Module activated"]); }
    public function deactivateModule(Request $request) { return $this->success(["message"=>"Module deactivated"]); }

    // ===== QUOTES =====
    public function getQuotes(Request $request)
    {
        $query = \App\Models\Quote::with("client","items");
        if ($request->filled("userid")) $query->where("client_id", $request->userid);
        if ($request->filled("status")) $query->where("status", $request->status);
        $quotes = $query->orderBy("id","desc")->paginate($request->get("limitnum",25));
        return $this->paginated($quotes);
    }

    public function createQuote(Request $request)
    {
        $validated = $request->validate(["clientid"=>"required|exists:clients,id", "valid_until"=>"nullable|date"]);
        $quote = \App\Models\Quote::create(["client_id"=>$validated["clientid"], "date"=>now()->format("Y-m-d"), "valid_until"=>$validated["valid_until"] ?? now()->addDays(30)->format("Y-m-d"), "subject"=>$request->get("subject","Quote"), "status"=>"draft", "subtotal"=>0, "tax"=>0, "total"=>0]);
        if ($request->has("items")) {
            $total = 0;
            foreach ((array)$request->items as $item) {
                $amount = (float)($item["amount"] ?? 0);
                $quote->items()->create(["description"=>$item["description"] ?? "", "amount"=>$amount, "quantity"=>(int)($item["quantity"] ?? 1), "taxed"=>($item["taxed"] ?? false)]);
                $total += $amount * (int)($item["quantity"] ?? 1);
            }
            $quote->update(["subtotal"=>$total, "total"=>$total]);
        }
        return $this->success(["quoteid" => $quote->id]);
    }

    public function updateQuote(Request $request) {
        $quote = \App\Models\Quote::find($request->quoteid);
        if (!$quote) return $this->error("Quote Not Found", 404);
        foreach (["status","valid_until","notes","customer_notes","proposal"] as $f) { if ($request->has($f)) $quote->$f = $request->$f; }
        $quote->save();
        return $this->success(["quoteid"=>$quote->id]);
    }

    public function deleteQuote(Request $request) {
        $quote = \App\Models\Quote::find($request->quoteid);
        if (!$quote) return $this->error("Quote Not Found", 404);
        $quote->items()->delete();
        $quote->delete();
        return $this->success();
    }

    public function sendQuote(Request $request) {
        $quote = \App\Models\Quote::find($request->quoteid);
        if (!$quote) return $this->error("Quote Not Found", 404);
        $quote->update(["status"=>"sent"]);
        return $this->success(["quoteid"=>$quote->id]);
    }

    public function acceptQuote(Request $request) {
        $quote = \App\Models\Quote::find($request->quoteid);
        if (!$quote) return $this->error("Quote Not Found", 404);
        $quote->update(["status"=>"accepted"]);
        return $this->success(["quoteid"=>$quote->id]);
    }

    // ===== PROJECTS =====
    public function getProjects(Request $request) {
        $query = \App\Models\Project::with("client","tasks","messages");
        if ($request->filled("userid")) $query->where("client_id", $request->userid);
        $projects = $query->orderBy("id","desc")->paginate($request->get("limitnum",25));
        return $this->paginated($projects);
    }

    public function getProject(Request $request) {
        $project = \App\Models\Project::with("client","tasks","messages")->find($request->id ?? $request->projectid);
        if (!$project) return $this->error("Project Not Found", 404);
        return $this->success(["project"=>$project->toArray()]);
    }

    public function createProject(Request $request) {
        $validated = $request->validate(["title"=>"required", "clientid"=>"required|exists:clients,id"]);
        $project = \App\Models\Project::create(["title"=>$validated["title"], "client_id"=>$validated["clientid"], "description"=>$request->description, "status"=>$request->get("status","active"), "admin_id"=>\App\Models\Admin::first()->id ?? 18080]);
        return $this->success(["projectid"=>$project->id]);
    }

    public function updateProject(Request $request) {
        $project = \App\Models\Project::find($request->id ?? $request->projectid);
        if (!$project) return $this->error("Project Not Found", 404);
        foreach (["title","description","status","due_date"] as $f) { if ($request->has($f)) $project->$f = $request->$f; }
        $project->save();
        return $this->success(["projectid"=>$project->id]);
    }

    public function addProjectMessage(Request $request) {
        $project = \App\Models\Project::find($request->project_id ?? $request->projectid);
        if (!$project) return $this->error("Project Not Found", 404);
        $msg = $project->messages()->create(["message"=>$request->message, "admin_id"=>\App\Models\Admin::first()->id ?? 18080]);
        return $this->success(["messageid"=>$msg->id]);
    }

    public function addProjectTask(Request $request) {
        $project = \App\Models\Project::find($request->project_id ?? $request->projectid);
        if (!$project) return $this->error("Project Not Found", 404);
        $task = $project->tasks()->create(["title"=>$request->title ?? "Task", "description"=>$request->description, "status"=>"pending"]);
        return $this->success(["taskid"=>$task->id]);
    }

    public function updateProjectTask(Request $request) {
        $task = \App\Models\ProjectTask::find($request->taskid);
        if (!$task) return $this->error("Task Not Found", 404);
        foreach (["title","description","status","due_date"] as $f) { if ($request->has($f)) $task->$f = $request->$f; }
        $task->save();
        return $this->success(["taskid"=>$task->id]);
    }

    public function deleteProjectTask(Request $request) {
        $task = \App\Models\ProjectTask::find($request->taskid);
        if (!$task) return $this->error("Task Not Found", 404);
        $task->delete();
        return $this->success();
    }

    public function startTaskTimer(Request $request) { return $this->success(["message"=>"Timer started"]); }
    public function endTaskTimer(Request $request) { return $this->success(["message"=>"Timer stopped"]); }

    // ===== AFFILIATES =====
    public function getAffiliates(Request $request) {
        $affiliates = \App\Models\Affiliate::with("client")->paginate($request->get("limitnum",25));
        return $this->paginated($affiliates);
    }

    public function affiliateActivate(Request $request) {
        $client = \App\Models\Client::find($request->clientid);
        if (!$client) return $this->error("Client Not Found", 404);
        $aff = \App\Models\Affiliate::firstOrCreate(["client_id"=>$client->id], ["pay_type"=>"percentage", "pay_amount"=>10, "balance"=>0]);
        return $this->success(["affiliateid"=>$aff->id]);
    }

    // ===== OAUTH =====
    public function listOAuthCredentials(Request $request) {
        $creds = \App\Models\ApiCredential::where("active", true)->get(["id","identifier","description","created_at"]);
        return $this->success(["credentials"=>$creds->toArray()]);
    }

    public function createOAuthCredential(Request $request) {
        $cred = \App\Models\ApiCredential::create(["admin_id"=>\App\Models\Admin::first()->id ?? 18080, "identifier"=>\Illuminate\Support\Str::random(32), "secret"=>\Illuminate\Support\Str::random(64), "description"=>$request->description, "active"=>true]);
        return $this->success(["credentialid"=>$cred->id, "identifier"=>$cred->identifier, "secret"=>$cred->secret]);
    }

    public function updateOAuthCredential(Request $request) {
        $cred = \App\Models\ApiCredential::find($request->credentialid);
        if (!$cred) return $this->error("Credential Not Found", 404);
        if ($request->has("description")) $cred->description = $request->description;
        if ($request->has("active")) $cred->active = $request->boolean("active");
        $cred->save();
        return $this->success(["credentialid"=>$cred->id]);
    }

    public function deleteOAuthCredential(Request $request) {
        $cred = \App\Models\ApiCredential::find($request->credentialid);
        if (!$cred) return $this->error("Credential Not Found", 404);
        $cred->delete();
        return $this->success();
    }
}