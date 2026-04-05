<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $ticket->client?->first_name ?? 'Customer']) }}</p>

<p>A new reply has been posted to your support ticket.</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Ticket ID</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">#{{ $ticket->tid ?? $ticket->id }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Subject</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $ticket->subject }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Reply From</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ ($isStaffReply ?? false) ? 'Staff' : 'Client' }}</td></tr>
</table>

<div style="background:#f8f9fa;padding:15px;border-radius:4px;margin:15px 0;">
<p style="margin:0;"><strong>Reply:</strong></p>
<p style="margin:5px 0 0 0;">{{ $replyMessage }}</p>
</div>

<p>Log in to your account to view the full conversation and respond.</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
