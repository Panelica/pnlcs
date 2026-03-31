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
        return $this->success([
            "health" => [
                "status" => "ok",
                "version" => "1.0.0",
                "laravel" => app()->version(),
                "php" => phpversion(),
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

    public function getEmails(Request $request)
    {
        $query = \App\Models\Email::query();
        if ($request->filled("userid")) $query->where("client_id", $request->userid);
        return $this->paginated($query->orderBy("id", "desc")->paginate($request->get("limitnum", 25)));
    }

    public function getServers()
    {
        return $this->success(["servers" => \App\Models\Server::all()->makeVisible([])->toArray()]);
    }

    public function getRegistrars()
    {
        $registrars = \Illuminate\Support\Facades\DB::table("registrar_settings")->select("registrar")->distinct()->pluck("registrar");
        return $this->success(["registrars" => $registrars->toArray()]);
    }

    public function getProducts()
    {
        return $this->success(["products" => \App\Models\Product::with("group")->get()->toArray()]);
    }

    public function getPromotions()
    {
        return $this->success(["promotions" => \App\Models\Promotion::all()->toArray()]);
    }

    public function getTodoItems(Request $request)
    {
        $query = \App\Models\TodoItem::query();
        if ($request->filled("status")) $query->where("status", $request->status);
        return $this->success(["items" => $query->orderBy("id", "desc")->get()->toArray()]);
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
        $gateways = \Illuminate\Support\Facades\DB::table("gateway_settings")->select("gateway")->distinct()->pluck("gateway");
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
