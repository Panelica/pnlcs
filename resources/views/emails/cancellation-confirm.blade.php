<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $service->client?->first_name ?? 'Customer']) }}</p>

<p>Your cancellation request has been confirmed.</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Product</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->product?->name ?? 'N/A' }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Domain</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->domain ?? 'N/A' }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Cancellation Type</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $cancellationType === 'immediate' ? 'Immediate' : 'End of Billing Period' }}</td></tr>
@if($cancellationType !== 'immediate' && $service->next_due_date)
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Effective Date</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->next_due_date->format('M d, Y') }}</td></tr>
@endif
</table>

@if($cancellationType === 'immediate')
<p>Your service will be terminated shortly.</p>
@else
<p>Your service will remain active until the end of the current billing period.</p>
@endif

<p>If you change your mind, please contact our support team before the effective date.</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
