<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $domain->client?->first_name ?? __('email.common.customer')]) }}</p>

<p>{{ __('email.domain_registration.success') }}</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.domain_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $domain->domain }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.domain_registration.registration_date') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $domain->registration_date?->format('M d, Y') ?? now()->format('M d, Y') }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.domain_registration.expiry_date') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $domain->expiry_date?->format('M d, Y') ?? 'N/A' }}</td></tr>
@if($domain->nameservers)
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.domain_registration.nameservers') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $domain->nameservers }}</td></tr>
@endif
</table>

<p>{{ __('email.domain_registration.manage') }}</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>