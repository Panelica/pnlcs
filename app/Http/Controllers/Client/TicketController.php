<?php
namespace App\Http\Controllers\Client;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    public function index() {
        $tickets = Ticket::with("department")->where("client_id", $this->getClientId())->orderBy("last_reply","desc")->paginate(25);
        return view("client.tickets.index", compact("tickets"));
    }
    public function create() {
        $departments = TicketDepartment::where("hidden", false)->orderBy("sort_order")->get();
        return view("client.tickets.create", compact("departments"));
    }
    public function store(Request $request) {
        $validated = $request->validate(["department_id" => "required|exists:ticket_departments,id", "subject" => "required|string|max:255", "message" => "required|string", "priority" => "nullable|in:low,medium,high"]);
        $ticket = Ticket::create(["tid" => strtoupper(Str::random(6)), "department_id" => $validated["department_id"], "client_id" => $this->getClientId(), "email" => auth()->user()->email, "name" => auth()->user()->full_name, "title" => $validated["subject"], "message" => $validated["message"], "priority" => $validated["priority"] ?? "medium", "status" => "open", "last_reply" => now()]);
        return redirect()->route("client.tickets.show", $ticket)->with("success", "Ticket opened.");
    }
    public function show(Ticket $ticket) {
        abort_if($ticket->client_id !== $this->getClientId(), 403);
        $ticket->load("department", "replies");
        return view("client.tickets.show", compact("ticket"));
    }
    public function reply(Request $request, Ticket $ticket) {
        abort_if($ticket->client_id !== $this->getClientId(), 403);
        $validated = $request->validate(["message" => "required|string"]);
        $ticket->replies()->create(["message" => $validated["message"], "client_id" => $this->getClientId()]);
        $ticket->update(["status" => "customer-reply", "last_reply" => now()]);
        return back()->with("success", "Reply added.");
    }
    private function getClientId() { return auth()->user()->clients()->first()?->id ?? 0; }
}
