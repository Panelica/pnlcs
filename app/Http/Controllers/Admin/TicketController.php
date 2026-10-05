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
        // Tickets with no account behind them: the public contact form, or
        // mail from an unknown sender. Where contact-form spam collects.
        if ($request->boolean('guests')) {
            $query->whereNull('client_id');
        }
        $tickets = $query->orderBy('last_reply', 'desc')->orderBy('created_at', 'desc')->paginate(25);
        $departments = TicketDepartment::all();

        return view('admin.tickets.index', compact('tickets', 'departments'));
    }

    /**
     * Open a ticket for a customer: the customer rang, wrote from somewhere
     * else, or staff need something from them. Nothing in the admin area
     * could; the client page's "New ticket" link went to the ticket list.
     */
    public function create(Request $request)
    {
        $client = $request->filled('client') ? $this->findClient((string) $request->input('client')) : null;

        return view('admin.tickets.create', [
            'client' => $client,
            'departments' => TicketDepartment::orderBy('sort_order')->get(['id', 'name']),
            'services' => $client ? \App\Models\Service::where('client_id', $client->id)->with('product:id,name')->orderByDesc('id')->get(['id', 'product_id', 'domain']) : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'client' => 'required|string|max:255',
            'department_id' => 'required|exists:ticket_departments,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'required|in:Low,Medium,High',
            'related_service' => 'nullable|integer',
        ]);

        $client = $this->findClient($v['client']);
        if (! $client) {
            return back()->withInput()->withErrors(['client' => __('admin.tickets.open_no_client')]);
        }
        if (! empty($v['related_service']) && ! \App\Models\Service::where('id', $v['related_service'])->where('client_id', $client->id)->exists()) {
            return back()->withInput()->withErrors(['related_service' => __('admin.tickets.open_service_not_theirs')]);
        }

        // Staff wrote the first message, so the next move is the customer's:
        // the ticket starts as Answered, like one staff have replied to.
        $ticket = app(\App\Services\TicketService::class)->createTicket([
            'department_id' => $v['department_id'],
            'client_id' => $client->id,
            'name' => trim($client->first_name.' '.$client->last_name),
            'email' => $client->email,
            'title' => $v['subject'],
            'message' => $v['message'],
            'priority' => $v['priority'],
            'admin' => (string) auth('admin')->user()?->username,
            'status' => 'Answered',
            'service' => ! empty($v['related_service']) ? (string) $v['related_service'] : null,
        ]);
        // As every other door does; isAdmin tells the customer's mail that
        // staff opened it, and leaves support's own alert out.
        event(new \App\Events\TicketOpened($ticket, true));

        return redirect()->route('admin.tickets.show', $ticket)->with('success', __('admin.tickets.open_done', ['tid' => $ticket->tid]));
    }

    private function findClient(string $input): ?\App\Models\Client
    {
        $input = trim($input);

        return ctype_digit($input) ? \App\Models\Client::find((int) $input) : \App\Models\Client::where('email', $input)->first();
    }

    public function show(Ticket $ticket)
    {
        $ticket->load('department', 'client', 'replies', 'notes', 'feedback');

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

    /** Delete one ticket, with everything attached to it. */
    public function destroy(Ticket $ticket)
    {
        $tid = $ticket->tid;
        app(\App\Services\TicketService::class)->deleteTicket($ticket, auth('admin')->user()->username);

        return redirect()->route('admin.tickets.index')->with('success', __('admin.tickets.deleted', ['tid' => $tid]));
    }

    /** Delete the tickets ticked on the list. */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ticket_ids' => 'required|array|min:1|max:500',
            'ticket_ids.*' => 'integer',
        ]);

        $service = app(\App\Services\TicketService::class);
        $by = auth('admin')->user()->username;
        $deleted = 0;
        foreach (Ticket::whereIn('id', $validated['ticket_ids'])->get() as $ticket) {
            $service->deleteTicket($ticket, $by);
            $deleted++;
        }

        return back()->with('success', __('admin.tickets.bulk_deleted', ['count' => $deleted]));
    }
}
