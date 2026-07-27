<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

@if($isAdmin ?? false)
<p>{{ __('email.ticket_opened.admin_new_ticket') }}</p>
@else
<p>{{ __('email.common.greeting', ['name' => $ticket->client?->first_name ?? __('email.common.customer')]) }}</p>
<p>{{ __('email.ticket_opened.client_success') }}</p>
@endif

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.ticket_opened.ticket_id') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">#{{ $ticket->tid ?? $ticket->id }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.subject_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $ticket->title }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.department_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $ticket->department?->name ?? __('email.common.general') }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.priority_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ ucfirst($ticket->priority ?? 'medium') }}</td></tr>
</table>

@if($ticket->message)
<div style="background:#f8f9fa;padding:15px;border-radius:4px;margin:15px 0;">
<p style="margin:0;"><strong>{{ __('email.common.message_label') }}:</strong></p>
<p style="margin:5px 0 0 0;">{{ Str::limit($ticket->message, 500) }}</p>
</div>
@endif

@if($isAdmin ?? false)
<p>{{ __('email.ticket_opened.admin_review') }}</p>
@else
<p>{{ __('email.ticket_opened.client_review') }}</p>
@endif

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>