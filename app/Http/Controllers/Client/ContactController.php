<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\ResolvesClient;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use App\Services\TicketService;
use App\Services\TicketSpamService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    use ResolvesClient;

    public function show()
    {
        $departments = TicketDepartment::where('hidden', false)
            ->orderBy('sort_order')
            ->get();

        return view('client.contact', compact('departments'));
    }

    public function submit(Request $request)
    {
        // Honeypot spam protection
        if ($request->filled('website_url')) {
            return back()->with('success', __('messages.success.thank_you'));
        }
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:200',
            'department_id' => 'required|exists:ticket_departments,id',
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:5000',
        ]);

        // Anyone can post this form, so it is the obvious way in for the
        // rubbish the spam screen is configured to keep out.
        if (app(TicketSpamService::class)->isSpam($validated['email'], $validated['subject'], $validated['message'])) {
            return back()->with('success', __('messages.success.your_message_has_been_sent_we_will_get_back_to_you'));
        }

        $clientId = auth()->check() ? $this->currentClient()?->id : null;

        // Through the one creator, which gives the ticket a six-digit reference
        // and checks it is free. A reference made here out of
        // strtoupper(Str::random(6)) is not one the mail import can match - it
        // reads six digits from the subject - and this is the door where that
        // matters most: whoever writes in gets the reference in their
        // ticket-opened email, replies to it, and the reply opened a second
        // ticket instead of joining the first.
        app(TicketService::class)->createTicket([
            'department_id' => $validated['department_id'],
            'client_id' => $clientId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'title' => $validated['subject'],
            'message' => $validated['message'],
            'priority' => 'Medium',
        ]);

        return back()->with('success', __('messages.success.your_message_has_been_sent_we_will_get_back_to_you'));
    }
}
