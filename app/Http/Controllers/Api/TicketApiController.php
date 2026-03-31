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
}
