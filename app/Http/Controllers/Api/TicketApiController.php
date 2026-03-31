<?php
namespace App\Http\Controllers\Api;

use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TicketApiController extends BaseApiController
{
    public function getTickets(Request $request)
    {
        $query = Ticket::with("department");
        if ($request->filled("status")) { $query->where("status", $request->status); }
        if ($request->filled("deptid")) { $query->where("department_id", $request->deptid); }
        if ($request->filled("userid")) { $query->where("client_id", $request->userid); }
        $tickets = $query->orderBy("last_reply", "desc")->paginate($request->get("limitnum", 25));
        return $this->paginated($tickets);
    }

    public function getTicket(Request $request)
    {
        $ticket = Ticket::with("department", "replies", "notes")->find($request->ticketid);
        if (!$ticket) return $this->error("Ticket Not Found", 404);
        return $this->success(["ticket" => $ticket->toArray()]);
    }

    public function openTicket(Request $request)
    {
        $validated = $request->validate([
            "deptid" => "required|exists:ticket_departments,id",
            "subject" => "required|string|max:255",
            "message" => "required|string",
            "userid" => "nullable|exists:clients,id",
            "name" => "nullable|string",
            "email" => "required|email",
            "priority" => "nullable|in:low,medium,high,critical",
        ]);
        $ticket = Ticket::create([
            "tid" => strtoupper(Str::random(6)),
            "department_id" => $validated["deptid"],
            "client_id" => $validated["userid"] ?? null,
            "name" => $validated["name"] ?? null,
            "email" => $validated["email"],
            "title" => $validated["subject"],
            "message" => $validated["message"],
            "priority" => $validated["priority"] ?? "medium",
            "status" => "open",
            "last_reply" => now(),
        ]);
        return $this->success(["tid" => $ticket->tid, "ticketid" => $ticket->id]);
    }

    public function addTicketReply(Request $request)
    {
        $ticket = Ticket::find($request->ticketid);
        if (!$ticket) return $this->error("Ticket Not Found", 404);
        $validated = $request->validate(["message" => "required|string"]);
        $reply = $ticket->replies()->create([
            "message" => $validated["message"],
            "admin" => $request->adminusername ?? null,
            "client_id" => $request->userid ?? null,
        ]);
        $ticket->update(["status" => $request->adminusername ? "answered" : "customer-reply", "last_reply" => now()]);
        return $this->success(["replyid" => $reply->id]);
    }

    public function addTicketNote(Request $request)
    {
        $ticket = Ticket::find($request->ticketid);
        if (!$ticket) return $this->error("Ticket Not Found", 404);
        $note = \App\Models\TicketNote::create(["ticket_id" => $ticket->id, "admin" => $request->adminusername ?? "system", "message" => $request->message]);
        return $this->success(["noteid" => $note->id]);
    }

    public function updateTicket(Request $request)
    {
        $ticket = Ticket::find($request->ticketid);
        if (!$ticket) return $this->error("Ticket Not Found", 404);
        foreach (["status", "priority", "department_id" => "deptid", "admin", "flag"] as $db => $api) {
            $key = is_numeric($db) ? $api : $db;
            $param = is_numeric($db) ? $api : $api;
            if ($request->has($param)) $ticket->$key = $request->$param;
        }
        $ticket->save();
        return $this->success(["ticketid" => $ticket->id]);
    }

    public function deleteTicket(Request $request)
    {
        $ticket = Ticket::find($request->ticketid);
        if (!$ticket) return $this->error("Ticket Not Found", 404);
        $ticket->delete();
        return $this->success();
    }

    public function getTicketCounts()
    {
        return $this->success(["counts" => [
            "all" => Ticket::count(),
            "open" => Ticket::where("status", "open")->count(),
            "answered" => Ticket::where("status", "answered")->count(),
            "customer_reply" => Ticket::where("status", "customer-reply")->count(),
            "on_hold" => Ticket::where("status", "on hold")->count(),
            "closed" => Ticket::where("status", "closed")->count(),
        ]]);
    }

    public function getSupportDepartments()
    {
        return $this->success(["departments" => \App\Models\TicketDepartment::orderBy("sort_order")->get()->toArray()]);
    }

    public function getSupportStatuses()
    {
        return $this->success(["statuses" => \App\Models\TicketStatus::orderBy("sort_order")->get()->toArray()]);
    }

    public function getTicketPredefinedCats()
    {
        return $this->success(["categories" => \App\Models\TicketPredefinedCategory::all()->toArray()]);
    }

    public function getTicketPredefinedReplies(Request $request)
    {
        $query = \App\Models\TicketPredefinedReply::query();
        if ($request->filled("catid")) $query->where("category_id", $request->catid);
        return $this->success(["replies" => $query->get()->toArray()]);
    }

    public function mergeTicket(Request $request)
    {
        $ticket = Ticket::find($request->ticketid);
        $mergeInto = Ticket::find($request->mergeid);
        if (!$ticket || !$mergeInto) return $this->error("Ticket Not Found", 404);
        foreach ($ticket->replies as $reply) { $reply->update(["ticket_id" => $mergeInto->id]); }
        $ticket->update(["status" => "closed", "merged_ticket_id" => $mergeInto->id]);
        return $this->success(["ticketid" => $mergeInto->id]);
    }
}
