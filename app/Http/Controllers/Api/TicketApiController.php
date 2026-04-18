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
        $query = Ticket::with('department');
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('deptid')) $query->where('department_id', $request->deptid);
        if ($request->filled('userid')) $query->where('client_id', $request->userid);
        return $this->paginated($query->orderBy('last_reply','desc')->paginate($request->get('limitnum',25)));
    }
    public function getTicket(Request $request)
    {
        $ticket = Ticket::with('department','replies','notes')->find($request->ticketid);
        if (!$ticket) return $this->error('Ticket Not Found', 404);
        return $this->success(['ticket' => $ticket->toArray()]);
    }
    public function openTicket(Request $request)
    {
        $v = $request->validate(['deptid'=>'required|exists:ticket_departments,id','subject'=>'required|string|max:255','message'=>'required|string','email'=>'required|email','priority'=>'nullable|in:low,medium,high,critical']);
        $ticket = Ticket::create(['tid'=>strtoupper(Str::random(6)),'department_id'=>$v['deptid'],'client_id'=>$request->userid,'name'=>$request->name,'email'=>$v['email'],'title'=>$v['subject'],'message'=>$v['message'],'priority'=>$v['priority']??'medium','status'=>'open','last_reply'=>now()]);
        return $this->success(['tid'=>$ticket->tid,'ticketid'=>$ticket->id]);
    }
    public function addTicketReply(Request $request)
    {
        $ticket = Ticket::find($request->ticketid);
        if (!$ticket) return $this->error('Ticket Not Found', 404);
        $v = $request->validate(['message'=>'required|string']);
        $reply = $ticket->replies()->create(['message'=>$v['message'],'admin'=>$request->adminusername,'client_id'=>$request->userid]);
        $ticket->update(['status'=>$request->adminusername?'answered':'customer-reply','last_reply'=>now()]);
        return $this->success(['replyid'=>$reply->id]);
    }
    public function addTicketNote(Request $request)
    {
        $ticket = Ticket::find($request->ticketid);
        if (!$ticket) return $this->error('Ticket Not Found', 404);
        $note = \App\Models\TicketNote::create(['ticket_id'=>$ticket->id,'admin'=>$request->adminusername??'system','message'=>$request->message]);
        return $this->success(['noteid'=>$note->id]);
    }
    public function updateTicket(Request $request)
    {
        $ticket = Ticket::find($request->ticketid);
        if (!$ticket) return $this->error('Ticket Not Found', 404);
        foreach(['status','priority','admin','flag'] as $f) { if($request->has($f)) $ticket->$f=$request->$f; }
        if($request->has('deptid')) $ticket->department_id=$request->deptid;
        $ticket->save();
        return $this->success(['ticketid'=>$ticket->id]);
    }
    public function deleteTicket(Request $request)
    {
        $ticket = Ticket::find($request->ticketid);
        if (!$ticket) return $this->error('Ticket Not Found', 404);
        $ticket->delete();
        return $this->success();
    }
    public function getTicketCounts()
    {
        return $this->success(['counts'=>['all'=>Ticket::count(),'open'=>Ticket::where('status','open')->count(),'answered'=>Ticket::where('status','answered')->count(),'customer_reply'=>Ticket::where('status','customer-reply')->count(),'on_hold'=>Ticket::where('status','on hold')->count(),'closed'=>Ticket::where('status','closed')->count()]]);
    }
    public function getSupportDepartments() { return $this->success(['departments'=>\App\Models\TicketDepartment::orderBy('sort_order')->get()->toArray()]); }
    public function getSupportStatuses() { return $this->success(['statuses'=>\App\Models\TicketStatus::orderBy('sort_order')->get()->toArray()]); }
    public function getTicketPredefinedCats() { return $this->success(['categories'=>\App\Models\TicketPredefinedCategory::all()->toArray()]); }
    public function getTicketPredefinedReplies(Request $request) { $q=\App\Models\TicketPredefinedReply::query(); if($request->filled('catid'))$q->where('category_id',$request->catid); return $this->success(['replies'=>$q->get()->toArray()]); }
    public function mergeTicket(Request $request)
    {
        $ticket = Ticket::find($request->ticketid);
        $mergeInto = Ticket::find($request->mergeid);
        if (!$ticket || !$mergeInto) return $this->error('Ticket Not Found', 404);
        foreach($ticket->replies as $reply) { $reply->update(['ticket_id'=>$mergeInto->id]); }
        $ticket->update(['status'=>'closed','merged_ticket_id'=>$mergeInto->id]);
        return $this->success(['ticketid'=>$mergeInto->id]);
    }
    public function getTicketNotes(Request $request)
    {
        $ticket = Ticket::find($request->ticketid);
        if (!$ticket) return $this->error('Ticket Not Found', 404);
        return $this->success(['notes'=>\App\Models\TicketNote::where('ticket_id',$ticket->id)->get()->toArray()]);
    }
    public function getTicketAttachment(Request $request) { return $this->success(['attachments'=>[]]); }
    public function updateTicketReply(Request $request)
    {
        $reply = TicketReply::find($request->replyid);
        if (!$reply) return $this->error('Reply Not Found', 404);
        if($request->has('message')) $reply->message=$request->message;
        $reply->save();
        return $this->success(['replyid'=>$reply->id]);
    }
    public function deleteTicketNote(Request $request)
    {
        $note = \App\Models\TicketNote::find($request->noteid);
        if (!$note) return $this->error('Note Not Found', 404);
        $note->delete();
        return $this->success();
    }
    public function deleteTicketReply(Request $request)
    {
        $reply = TicketReply::find($request->replyid);
        if (!$reply) return $this->error('Reply Not Found', 404);
        $reply->delete();
        return $this->success();
    }
    public function blockTicketSender(Request $request)
    {
        $ticket = Ticket::find($request->ticketid);
        if (!$ticket) return $this->error('Ticket Not Found', 404);
        if ($ticket->email) {
            \App\Models\BannedEmail::firstOrCreate(['domain'=>$ticket->email],['reason'=>'Blocked via ticket #'.$ticket->tid]);
        }
        return $this->success(['ticketid'=>$ticket->id]);
    }
}
