<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $service->client?->first_name ?? __('email.common.customer')]) }}</p>

<p>{{ __('email.service_welcome.provisioned') }}</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.product_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->product?->name ?? 'N/A' }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.domain_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->domain ?? 'N/A' }}</td></tr>
@if($service->username)
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.username_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->username }}</td></tr>
@endif
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.service_welcome.next_due_date') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->next_due_date?->format(date_fmt()) ?? 'N/A' }}</td></tr>
</table>

@if(($panelUrl ?? null) && $service->username)
<h3 style="color:#405189;margin:24px 0 6px;">{{ __('email.service_welcome.access_heading') }}</h3>
<p style="font-size:13px;color:#555;margin:0 0 10px;">{{ __('email.service_welcome.credentials_intro') }}</p>

<table style="width:100%;border-collapse:collapse;margin:0 0 16px;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;width:40%;"><strong>{{ __('email.service_welcome.control_panel') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;"><a href="{{ $panelUrl }}">{{ $panelUrl }}</a></td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.username_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->username }}</td></tr>
@if($password ?? null)
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.service_welcome.password_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;"><code>{{ $password }}</code></td></tr>
@endif
</table>

@if(($sshLevel ?? null) && ($accessHost ?? null))
<h3 style="color:#405189;margin:20px 0 6px;">{{ __('email.service_welcome.ssh_heading') }}</h3>
<table style="width:100%;border-collapse:collapse;margin:0 0 16px;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;width:40%;"><strong>{{ __('email.service_welcome.host_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $accessHost }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.common.username_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $service->username }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>{{ __('email.service_welcome.ssh_type_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $sshLevel === 'full' ? __('email.service_welcome.ssh_type_full') : __('email.service_welcome.ssh_type_sftp') }}</td></tr>
</table>
<p style="font-size:12px;color:#888;margin:0 0 12px;">{{ __('email.service_welcome.port_note') }}</p>
@endif

@if(!empty($nameservers))
<table style="width:100%;border-collapse:collapse;margin:0 0 16px;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;width:40%;"><strong>{{ __('email.service_welcome.nameservers_label') }}</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ implode(', ', $nameservers) }}</td></tr>
</table>
@endif
@endif

<p>{{ __('email.service_welcome.manage_service') }}</p>

@include('emails.partials.action', ['url' => route('client.services.show', $service->id), 'label' => __('email.common.view_service')])

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>