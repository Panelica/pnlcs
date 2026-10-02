<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>Your account was signed in to from a device we have not seen before.</p>

<table style="font-size:14px;border-collapse:collapse;">
<tr><td style="padding:4px 12px 4px 0;color:#666;">When</td><td>{{ $loginTime }}</td></tr>
<tr><td style="padding:4px 12px 4px 0;color:#666;">Device</td><td>{{ $loginDevice }}</td></tr>
<tr><td style="padding:4px 12px 4px 0;color:#666;">IP address</td><td>{{ $loginIp }}</td></tr>
</table>

<p>If this was you, there is nothing to do.</p>

<p style="font-size:13px;color:#666;">If it was not, change your password and sign out the sessions you do not recognise: <a href="{{ $securityUrl }}">{{ $securityUrl }}</a></p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
