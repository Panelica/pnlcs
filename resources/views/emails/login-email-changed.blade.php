<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('email.login_email_changed.changed', ['old' => $previousEmail, 'new' => $newEmail]) }}</p>

<p>{{ __('email.login_email_changed.how') }}</p>

<p style="font-size:13px;color:#666;">{{ __('email.login_email_changed.not_you') }}</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
