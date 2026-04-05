<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $invoice->client?->first_name ?? 'Customer']) }}</p>

<p>We have received your payment. Thank you!</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Invoice #</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $invoice->invoice_num ?? $invoice->id }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Amount Paid</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">${{ number_format((float)$invoice->total, 2) }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Transaction ID</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $transactionId ?? 'N/A' }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Payment Date</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $invoice->paid_at?->format('M d, Y') ?? now()->format('M d, Y') }}</td></tr>
</table>

<p>This invoice has been marked as paid. You can view the details in your client area.</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
