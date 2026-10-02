<?php

namespace App\Http\Controllers\Admin;

use App\Events\TicketReplied;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::with('department', 'client');
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        $tickets = $query->orderBy('last_reply', 'desc')->orderBy('created_at', 'desc')->paginate(25);
        $departments = TicketDepartment::all();

        return view('admin.tickets.index', compact('tickets', 'departments'));
    }

    public function show(Ticket $ticket)
    {
        $ticket->load('department', 'client', 'replies', 'notes', 'feedback');

        return view('admin.tickets.show', compact('ticket'));
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $validated = $request->validate(['message' => 'required|string']);
        $ticket->replies()->create([
            'message' => $validated['message'],
            'admin' => auth('admin')->user()->username,
        ]);
        $ticket->recordReply('Answered');
        event(new TicketReplied($ticket, $validated['message'], true));

        return back()->with('success', __('admin.messages.reply_added'));
    }

    /**
     * Add a staff-only note to the ticket. Notes were shown on this page but
     * could only be written through the API or by escalation rules.
     */
    public function storeNote(Request $request, Ticket $ticket)
    {
        $validated = $request->validate(['note' => 'required|string|max:10000']);

        $ticket->notes()->create([
            'admin' => auth('admin')->user()->username,
            'message' => $validated['note'],
        ]);

        return back()->with('success', __('admin.tickets.note_added'));
    }

    /** Remove a note: only the staff member who wrote it. */
    public function destroyNote(Ticket $ticket, \App\Models\TicketNote $note)
    {
        abort_unless($note->ticket_id === $ticket->id, 404);
        abort_unless($note->admin === auth('admin')->user()->username, 403);

        $note->delete();

        return back()->with('success', __('admin.tickets.note_deleted'));
    }
}
