<?php

namespace App\Http\Controllers\Client;

use App\Events\TicketOpened;
use App\Events\TicketReplied;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Services\TicketSpamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    public function index()
    {
        $tickets = Ticket::with('department')->where('client_id', $this->getClientId())->orderBy('last_reply', 'desc')->paginate(25);

        return view('client.tickets.index', compact('tickets'));
    }

    public function create()
    {
        $departments = TicketDepartment::where('hidden', false)->orderBy('sort_order')->get();

        // The form has always had a picker for the service the ticket is
        // about; nothing ever gave it anything to show.
        $services = Service::with('product')
            ->where('client_id', $this->getClientId())
            ->whereNotIn('status', ['terminated', 'cancelled', 'fraud'])
            ->orderBy('domain')
            ->get();

        return view('client.tickets.create', compact('departments', 'services'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department_id' => 'required|exists:ticket_departments,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'nullable|in:low,medium,high',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,png,gif,pdf,doc,docx,txt,zip',
        ]);

        $spamService = app(TicketSpamService::class);
        if ($spamService->isSpam($request->input('email', auth()->user()->email), $validated['subject'], $validated['message'])) {
            return back()->with('error', __('messages.error.message_flagged_as_spam'));
        }

        $ticket = Ticket::create([
            'tid' => strtoupper(Str::random(6)),
            'department_id' => $validated['department_id'],
            'client_id' => $this->getClientId(),
            'email' => auth()->user()->email,
            'name' => auth()->user()->full_name,
            'title' => $validated['subject'],
            'message' => $validated['message'],
            'priority' => $validated['priority'] ?? 'medium',
            'status' => 'Open',
            'last_reply' => now(),
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store("ticket-attachments/{$ticket->id}", 'local');
            $ticket->update(['attachment' => $path]);
        }

        event(new TicketOpened($ticket, false));

        return redirect()->route('client.tickets.show', $ticket)->with('success', __('messages.success.ticket_opened'));
    }

    public function show(Ticket $ticket)
    {
        abort_if($ticket->client_id !== $this->getClientId(), 403);
        $ticket->load('department', 'replies');

        return view('client.tickets.show', compact('ticket'));
    }

    public function reply(Request $request, Ticket $ticket)
    {
        abort_if($ticket->client_id !== $this->getClientId(), 403);
        $validated = $request->validate([
            'message' => 'required|string',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,png,gif,pdf,doc,docx,txt,zip',
        ]);

        $replyData = ['message' => $validated['message'], 'client_id' => $this->getClientId()];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store("ticket-attachments/{$ticket->id}", 'local');
            $replyData['attachment'] = $path;
        }

        $ticket->replies()->create($replyData);
        // Clearing the escalation marker lets a later period of silence escalate
        // again; leaving it set would freeze escalation for the ticket's life.
        // Clearing escalated_at lets a later period of silence escalate again;
        // leaving it set would freeze escalation for the ticket's life. flag is
        // the assigned admin and must survive a reply.
        $ticket->recordReply('Customer-Reply');
        event(new TicketReplied($ticket, $validated['message'], false));

        return back()->with('success', __('messages.success.reply_added'));
    }

    public function downloadAttachment(Ticket $ticket, ?int $replyId = null)
    {
        abort_if($ticket->client_id !== $this->getClientId(), 403);

        if ($replyId) {
            $reply = $ticket->replies()->findOrFail($replyId);
            $path = $reply->attachment;
        } else {
            $path = $ticket->attachment;
        }

        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, basename($path));
    }

    private function getClientId()
    {
        return auth()->user()->clients()->first()?->id ?? 0;
    }
}
