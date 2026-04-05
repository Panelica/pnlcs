<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

@if($isAdmin ?? false)
<p>A new support ticket has been opened.</p>
@else
<p>{{ __('email.common.greeting', ['name' => $ticket->client?->first_name ?? 'Customer']) }}</p>
<p>Your support ticket has been opened successfully.</p>
@endif

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Ticket ID</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">#{{ $ticket->tid ?? $ticket->id }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Subject</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $ticket->subject }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Department</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $ticket->department?->name ?? 'General' }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Priority</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ ucfirst($ticket->priority ?? 'medium') }}</td></tr>
</table>

@if($ticket->message)
<div style="background:#f8f9fa;padding:15px;border-radius:4px;margin:15px 0;">
<p style="margin:0;"><strong>Message:</strong></p>
<p style="margin:5px 0 0 0;">{{ Str::limit($ticket->message, 500) }}</p>
</div>
@endif

@if($isAdmin ?? false)
<p>Please review and respond to this ticket.</p>
@else
<p>Our team will review your ticket and respond as soon as possible.</p>
@endif

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
