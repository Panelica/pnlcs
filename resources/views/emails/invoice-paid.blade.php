<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $invoice->client?->first_name ?? __('email.common.customer')]) }}</p>

<p>{{ __('email.invoice_paid.received') }}</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.invoice_number_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $invoice->invoice_num ?? $invoice->id }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.invoice_paid.amount_paid') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">${{ number_format((float)$invoice->total, 2) }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.invoice_paid.transaction_id') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $transactionId ?? 'N/A' }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.invoice_paid.payment_date') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $invoice->paid_at?->format(date_fmt()) ?? now()->format(date_fmt()) }}</td></tr>
</table>

<p>{{ __('email.invoice_paid.marked_paid') }}</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>