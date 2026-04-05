<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $invoice->client?->first_name ?? 'Customer']) }}</p>

<p>A new invoice has been generated for your account.</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Invoice #</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $invoice->invoice_num ?? $invoice->id }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Amount</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">${{ number_format((float)$invoice->total, 2) }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Due Date</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $invoice->due_date?->format('M d, Y') ?? 'N/A' }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Status</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ ucfirst($invoice->status) }}</td></tr>
</table>

<p>{{ __('email.common.login_link') }}</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
