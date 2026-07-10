<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Email;

class EmailController extends Controller
{
    public function index()
    {
        $emails = Email::where('client_id', $this->getClientId())
            ->orderByDesc('id')
            ->paginate(25);

        return view('client.emails.index', compact('emails'));
    }

    public function show(Email $email)
    {
        abort_if($email->client_id !== $this->getClientId(), 403);

        return view('client.emails.show', compact('email'));
    }

    private function getClientId()
    {
        return auth()->user()->clients()->first()?->id ?? 0;
    }
}
