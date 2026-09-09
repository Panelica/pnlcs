<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>{{ __('client.email_verify.mail_greeting', ['name' => $firstName]) }}</p>

<p>{{ __('client.email_verify.mail_intro', ['email' => $email]) }}</p>

<p style="margin:24px 0;">
  <a href="{{ $verifyUrl }}" style="background:#405189;color:#fff;padding:12px 22px;border-radius:6px;text-decoration:none;display:inline-block;">{{ __('client.email_verify.mail_button') }}</a>
</p>

<p style="font-size:12px;color:#666;">{{ __('client.email_verify.mail_fallback') }}<br>{{ $verifyUrl }}</p>

<p style="font-size:12px;color:#666;">{{ __('client.email_verify.mail_ignore') }}</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
