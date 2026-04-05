<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use Illuminate\Http\Request;
use App\Events\TicketReplied;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::with("department", "client");
        if ($request->filled("status")) { $query->where("status", $request->status); }
        if ($request->filled("department_id")) { $query->where("department_id", $request->department_id); }
        if ($request->filled("priority")) { $query->where("priority", $request->priority); }
        $tickets = $query->orderBy("last_reply", "desc")->orderBy("created_at", "desc")->paginate(25);
        $departments = TicketDepartment::all();
        return view("admin.tickets.index", compact("tickets", "departments"));
    }

    public function show(Ticket $ticket)
    {
        $ticket->load("department", "client", "replies", "notes");
        return view("admin.tickets.show", compact("ticket"));
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $validated = $request->validate(["message" => "required|string"]);
        $ticket->replies()->create([
            "message" => $validated["message"],
            "admin" => auth("admin")->user()->username,
        ]);
        $ticket->update(["status" => "answered", "last_reply" => now()]);
        event(new TicketReplied($ticket, $validated["message"], true));
        return back()->with("success", "Reply added.");
    }
}
