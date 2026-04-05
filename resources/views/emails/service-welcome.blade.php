<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $service->client?->first_name ?? 'Customer']) }}</p>

<p>Your service has been provisioned and is now active.</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Product</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->product?->name ?? 'N/A' }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Domain</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->domain ?? 'N/A' }}</td></tr>
@if($service->username)
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Username</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->username }}</td></tr>
@endif
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Next Due Date</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->next_due_date?->format('M d, Y') ?? 'N/A' }}</td></tr>
</table>

<p>You can manage your service from your client area. If you need any assistance, please open a support ticket.</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
