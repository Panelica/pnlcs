<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.common.greeting', ['name' => $client->first_name ?? 'Customer']) }}</p>

<p>Welcome to <strong>{{ $companyName }}</strong>! Your account has been successfully created.</p>

<table style="width:100%;border-collapse:collapse;margin:20px 0;">
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Name</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $client->first_name }} {{ $client->last_name }}</td></tr>
<tr><td style="padding:8px;border-bottom:1px solid #eee;"><strong>Email</strong></td><td style="padding:8px;border-bottom:1px solid #eee;">{{ $client->email }}</td></tr>
</table>

<p>You can now log in to your client area to manage your services, view invoices, and submit support tickets.</p>

<p>If you have any questions, feel free to open a support ticket.</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
