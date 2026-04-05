<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $domain->client?->first_name ?? 'Customer']) }}</p>

<p>Your domain has been successfully registered.</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Domain</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $domain->domain }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Registration Date</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $domain->registration_date?->format('M d, Y') ?? now()->format('M d, Y') }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Expiry Date</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $domain->expiry_date?->format('M d, Y') ?? 'N/A' }}</td></tr>
@if($domain->nameservers)
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Nameservers</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $domain->nameservers }}</td></tr>
@endif
</table>

<p>You can manage your domain settings from your client area.</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
