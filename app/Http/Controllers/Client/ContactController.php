<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketDepartment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContactController extends Controller
{
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
        if ($request->filled("website_url")) { return back()->with("success", "Thank you."); }
        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'email'         => 'required|email|max:200',
            'department_id' => 'required|exists:ticket_departments,id',
            'subject'       => 'required|string|max:200',
            'message'       => 'required|string|max:5000',
        ]);

        $clientId = auth()->check() ? auth()->user()->clients()->first()?->id : null;

        Ticket::create([
            'tid'           => strtoupper(Str::random(6)),
            'department_id' => $validated['department_id'],
            'client_id'     => $clientId,
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'title'         => $validated['subject'],
            'message'       => $validated['message'],
            'status'        => 'Open',
            'priority'      => 'Medium',
            'last_reply'    => now(),
        ]);

        return back()->with('success', __('messages.success.your_message_has_been_sent_we_will_get_back_to_you'));
    }
}
