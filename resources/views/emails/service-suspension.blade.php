<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $service->client?->first_name ?? 'Customer']) }}</p>

<p style="color:#dc3545;"><strong>Your service has been suspended.</strong></p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Product</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->product?->name ?? 'N/A' }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Domain</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->domain ?? 'N/A' }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Reason</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $reason ?? 'Unpaid invoice' }}</td></tr>
</table>

<p>To reactivate your service, please log in to your account and settle any outstanding invoices.</p>

<p>If you believe this is an error, please contact our support team.</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
