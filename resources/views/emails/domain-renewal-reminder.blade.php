<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $domain->client?->first_name ?? 'Customer']) }}</p>

<p>Your domain <strong>{{ $domain->domain }}</strong> is expiring soon.</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Domain</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $domain->domain }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Expiry Date</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $domain->expiry_date?->format('M d, Y') ?? 'N/A' }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Days Until Expiry</strong></td><td style="padding:8px;border-bottom:1px solid #eee;color:#f7b84b;"><strong>{{ $daysUntilExpiry }}</strong></td></tr>
</table>

<p>{{ __('email.common.login_link') }} to renew your domain before it expires to avoid any disruption.</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
