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
        $ticket->load('department', 'client', 'replies', 'notes');

        // For the options panel.
        $options = auth('admin')->user()?->hasPermission('manage_tickets') ? [
            'statuses' => self::statusTitles(),
            'departments' => \App\Models\TicketDepartment::orderBy('sort_order')->get(['id', 'name']),
            'staff' => \App\Models\Admin::orderBy('username')->get(['id', 'username', 'first_name', 'last_name']),
        ] : null;

        return view('admin.tickets.show', compact('ticket', 'options'));
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

    /** The statuses a ticket can be put in: the configured ones, or the usual set before any are configured. */
    public static function statusTitles(): array
    {
        $titles = \App\Models\TicketStatus::orderBy('sort_order')->pluck('title')->all();

        return $titles !== [] ? $titles : ['Open', 'Answered', 'Customer-Reply', 'On Hold', 'In Progress', 'Closed'];
    }

    /**
     * Change a ticket's status, priority, department or assignee from its
     * page. The page could only reply; closing a ticket, raising its priority
     * or handing it to a colleague needed the API.
     */
    public function update(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'status' => ['required', \Illuminate\Validation\Rule::in(self::statusTitles())],
            'priority' => 'required|in:Low,Medium,High',
            'department_id' => 'required|exists:ticket_departments,id',
            'flag' => 'nullable|integer|exists:admins,id',
        ]);

        $wasClosed = strtolower((string) $ticket->status) === 'closed';
        $closing = strtolower($validated['status']) === 'closed' && ! $wasClosed;

        $ticket->update([
            'priority' => $validated['priority'],
            'department_id' => $validated['department_id'],
            'flag' => $validated['flag'] ?? null,
        ]);

        // Closing goes through the service so TicketClosed (and its hook) fires.
        if ($closing) {
            app(\App\Services\TicketService::class)->closeTicket($ticket);
        } else {
            $ticket->update(['status' => $validated['status']]);
        }

        \App\Models\ActivityLog::log("Ticket #{$ticket->tid} updated: status {$validated['status']}, priority {$validated['priority']}", auth('admin')->user()->username, $ticket->client_id);

        return back()->with('success', __('admin.tickets.options_saved'));
    }
}
