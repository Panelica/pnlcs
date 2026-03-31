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
        return $this->paginated($query->orderBy("id", "desc")->paginate($request->get("limitnum", 25)));
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
        return $this->paginated($query->orderBy("id", "desc")->paginate($request->get("limitnum", 25)));
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
        return $this->paginated($query->orderBy("id", "desc")->paginate($request->get("limitnum", 25)));
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
}
