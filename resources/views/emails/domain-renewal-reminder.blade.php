<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $domain->client?->first_name ?? __('email.common.customer')]) }}</p>

<p>{{ __('email.domain_renewal.expiring_soon', ['domain' => $domain->domain]) }}</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.domain_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $domain->domain }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.domain_registration.expiry_date') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $domain->expiry_date?->format(date_fmt()) ?? 'N/A' }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#f7b84b;"><strong>{{ __('email.domain_renewal.days_until_expiry') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;color:#f7b84b;"><strong>{{ $daysUntilExpiry }}</strong></td></tr>
</table>

<p>{{ __('email.common.login_link') }} {{ __('email.domain_renewal.renew_before') }}</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>