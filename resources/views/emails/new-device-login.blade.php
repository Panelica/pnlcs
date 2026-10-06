<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.new_device_login.intro') }}</p>

<table style="font-size:14px;border-collapse:collapse;">
<tr><td style="padding:4px 12px 4px 0;color:#666;">{{ __('email.new_device_login.when') }}</td><td>{{ $loginTime }}</td></tr>
<tr><td style="padding:4px 12px 4px 0;color:#666;">{{ __('email.new_device_login.device') }}</td><td>{{ $loginDevice }}</td></tr>
<tr><td style="padding:4px 12px 4px 0;color:#666;">{{ __('email.new_device_login.ip') }}</td><td>{{ $loginIp }}</td></tr>
</table>

<p>{{ __('email.new_device_login.was_you') }}</p>

<p style="font-size:13px;color:#666;">{{ __('email.new_device_login.not_you') }} <a href="{{ $securityUrl }}">{{ $securityUrl }}</a></p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
