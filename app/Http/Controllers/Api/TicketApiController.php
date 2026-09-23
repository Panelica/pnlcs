<?php
namespace App\Http\Controllers\Api;
use App\Events\TicketOpened;
use App\Events\TicketReplied;
use App\Models\Ticket;
use App\Services\TicketService;
use App\Models\TicketReply;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TicketApiController extends BaseApiController
{
    public function getTickets(Request $request)
    {
        // clientid is the WHMCS name for the customer filter.
        $this->alias($request, 'clientid', 'userid');
        $query = Ticket::with('department');
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('deptid')) $query->where('department_id', $request->deptid);
        if ($request->filled('userid')) $query->where('client_id', $request->userid);
        return $this->paginated($query->orderBy('last_reply','desc')->paginate($this->getPerPage(), ['*'], 'page', $this->getPage()));
    }
    public function getTicket(Request $request)
    {
        $ticket = Ticket::with('department','replies','notes')->find($request->ticketid);
        if (!$ticket) return $this->error('Ticket Not Found', 404);
        return $this->success(['ticket' => $ticket->toArray()]);
    }
    public function openTicket(Request $request)
    {
        $this->alias($request, 'clientid', 'userid');
        $v = $request->validate(['deptid'=>'required|exists:ticket_departments,id','subject'=>'required|string|max:255','message'=>'required|string','email'=>'required|email','priority'=>'nullable|in:low,medium,high,critical','userid'=>'nullable|integer|exists:clients,id']);
        // Through the one creator: six digits, checked to be free, which is
        // what the mail import matches a reply against.
        $ticket = app(TicketService::class)->createTicket(['department_id'=>$v['deptid'],'client_id'=>$request->userid,'name'=>$request->name,'email'=>$v['email'],'title'=>$v['subject'],'message'=>$v['message'],'priority'=>$v['priority']??'medium']);
        // The same event every other door raises: the acknowledgement to the
        // customer and the alert to support.
        event(new TicketOpened($ticket, (bool) $request->adminusername));

        return $this->success(['tid'=>$ticket->tid,'ticketid'=>$ticket->id]);
    }
    public function addTicketReply(Request $request)
    {
        $ticket = Ticket::find($request->ticketid);
        if (!$ticket) return $this->error('Ticket Not Found', 404);
        $v = $request->validate(['message'=>'required|string']);
        // Through the one place that adds a reply, which also picks the status
        // the rest of the application writes - 'Answered' or 'Customer-Reply',
        // not the lower-case pair this used to write.
        // WHMCS: with adminusername the reply is staff's, without it the
        // customer's. A staff reply is signed by the member of staff the
        // credential belongs to, as the panel signs it - the name the caller
        // put in adminusername could be anybody's.
        $asStaff = filled($request->adminusername);
        $reply = app(TicketService::class)->addReply($ticket, [
            'message' => $v['message'],
            'admin' => $asStaff ? (string) auth('admin')->user()?->username : null,
            'client_id' => $asStaff ? null : ($request->userid ?? $ticket->client_id),
        ]);

        // And the event the other three doors raise. Without it the reply was
        // written into the ticket and nobody was told: no answer emailed to the
        // customer, no notification rule fired, and the panel showing the
        // ticket as answered.
        event(new TicketReplied($ticket->fresh(), $v['message'], $asStaff));

        return $this->success(['replyid'=>$reply->id]);
    }
    public function addTicketNote(Request $request)
    {
        $ticket = Ticket::find($request->ticketid);
        if (!$ticket) return $this->error('Ticket Not Found', 404);
        // Signed by the caller, and never empty: the author used to be
        // whatever adminusername said.
        $v = $request->validate(['message'=>'required|string']);
        $note = \App\Models\TicketNote::create(['ticket_id'=>$ticket->id,'admin'=>(string) (auth('admin')->user()?->username ?? 'API'),'message'=>$v['message']]);
        return $this->success(['noteid'=>$note->id]);
    }
    public function updateTicket(Request $request)
    {
        $ticket = Ticket::find($request->ticketid);
        if (!$ticket) return $this->error('Ticket Not Found', 404);
        // The status has to be one of the configured ones - the lists and the
        // counts match on it, and an unknown word dropped the ticket out of
        // all of them - and the department and assignee have to exist.
        $request->validate([
            'status' => ['sometimes', 'required', 'string', function ($attribute, $value, $fail) {
                if (! \App\Models\TicketStatus::whereRaw('LOWER(title) = ?', [strtolower((string) $value)])->exists()) {
                    $fail('The status is not one of the ticket statuses configured here.');
                }
            }],
            'priority' => 'sometimes|required|in:low,medium,high,critical',
            'deptid' => 'sometimes|required|exists:ticket_departments,id',
            'flag' => 'sometimes|nullable|integer|exists:admins,id',
            'subject' => 'sometimes|required|string|max:255',
        ]);
        if ($request->has('subject')) {
            $ticket->title = $request->subject;
        }
        if ($request->has('status')) {
            // Stored as the status is spelled in its own table.
            $ticket->status = \App\Models\TicketStatus::whereRaw('LOWER(title) = ?', [strtolower((string) $request->status)])->value('title');
        }
        foreach(['priority','admin','flag'] as $f) { if($request->has($f)) $ticket->$f=$request->$f; }
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
        // Merging a ticket into itself closed it and moved nothing.
        if ($ticket->id === $mergeInto->id) return $this->error('A ticket cannot be merged into itself.', 422);
        // The notes go with the replies - staff's own record of the
        // conversation was left behind on a closed ticket - and the move
        // happens whole or not at all.
        DB::transaction(function () use ($ticket, $mergeInto) {
            TicketReply::where('ticket_id', $ticket->id)->update(['ticket_id' => $mergeInto->id]);
            \App\Models\TicketNote::where('ticket_id', $ticket->id)->update(['ticket_id' => $mergeInto->id]);
            $ticket->update(['status' => 'Closed', 'merged_ticket_id' => $mergeInto->id]);
        });
        return $this->success(['ticketid'=>$mergeInto->id]);
    }
    public function getTicketNotes(Request $request)
    {
        $ticket = Ticket::find($request->ticketid);
        if (!$ticket) return $this->error('Ticket Not Found', 404);
        return $this->success(['notes'=>\App\Models\TicketNote::where('ticket_id',$ticket->id)->get()->toArray()]);
    }
    /**
     * The files on a ticket.
     *
     * Without attachmentindex the call lists what is there; with it, the file
     * itself comes back base64 encoded, which is how the WHMCS-compatible
     * clients expect to read one.
     */
    public function getTicketAttachment(Request $request)
    {
        $ticket = Ticket::with('replies')->find($request->ticketid);
        if (! $ticket) {
            return $this->error('Ticket Not Found', 404);
        }

        $files = [];
        if ($ticket->attachment) {
            $files[] = ['replyid' => null, 'path' => $ticket->attachment];
        }
        foreach ($ticket->replies->whereNotNull('attachment') as $reply) {
            $files[] = ['replyid' => $reply->id, 'path' => $reply->attachment];
        }

        $listed = [];
        foreach ($files as $index => $file) {
            $listed[] = [
                'index' => $index,
                'replyid' => $file['replyid'],
                'filename' => basename($file['path']),
            ];
        }

        if (! $request->has('attachmentindex')) {
            return $this->success(['attachments' => $listed]);
        }

        $index = (int) $request->attachmentindex;
        $disk = \Illuminate\Support\Facades\Storage::disk('local');

        if (! isset($files[$index]) || ! $disk->exists($files[$index]['path'])) {
            return $this->error('Attachment Not Found', 404);
        }

        return $this->success([
            'filename' => basename($files[$index]['path']),
            'data' => base64_encode($disk->get($files[$index]['path'])),
        ]);
    }
    public function updateTicketReply(Request $request)
    {
        $reply = TicketReply::find($request->replyid);
        if (!$reply) return $this->error('Reply Not Found', 404);
        // An empty message reached a NOT NULL column as a 500.
        $request->validate(['message' => 'sometimes|required|string']);
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
        // Blocking a sender means no more tickets from them, which is the
        // ticket spam filter - what the mail import and the ticket form both
        // check. This wrote to the signup ban list instead: the sender could no
        // longer open an account, and went on opening tickets.
        if (! $ticket->email) {
            return $this->error('This ticket has no sender address to block.', 422);
        }
        \App\Models\TicketSpamFilter::firstOrCreate(['type' => 'email', 'content' => strtolower($ticket->email)]);
        if ($request->boolean('delete')) {
            $ticket->delete();
        }
        return $this->success(['ticketid'=>$ticket->id, 'blocked'=>strtolower($ticket->email)]);
    }
}
