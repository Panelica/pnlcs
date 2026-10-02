<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('client.confirm.mail_intro') }}</p>

<p style="font-size:28px;font-weight:bold;letter-spacing:6px;margin:24px 0;">{{ $code }}</p>

<p style="font-size:13px;color:#666;">{{ __('client.confirm.mail_expiry', ['minutes' => $minutes]) }}</p>

<p style="font-size:13px;color:#666;">{{ __('client.confirm.mail_not_you') }}</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
