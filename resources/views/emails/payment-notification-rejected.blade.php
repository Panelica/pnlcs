<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $notification->client?->first_name ?? __('email.common.customer')]) }}</p>

<p>{{ __('email.payment_notification_rejected.intro', ['number' => $invoice->invoice_num ?? $invoice->id]) }}</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.invoice_number_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $invoice->invoice_num ?? $invoice->id }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.payment_notification_rejected.reported_amount') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">${{ number_format((float)$notification->amount, 2) }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.payment_notification_rejected.reason') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;color:#dc3545;">{{ $notification->admin_note }}</td></tr>
</table>

<p>{{ __('email.payment_notification_rejected.next_steps') }}</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
