<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>We received a request to reset the password for your account ({{ $email }}).</p>

<p>Click the button below to choose a new password. This link expires in 60 minutes.</p>

<p style="margin:24px 0;">
  <a href="{{ $resetUrl }}" style="background:#405189;color:#fff;padding:12px 22px;border-radius:6px;text-decoration:none;display:inline-block;">Reset password</a>
</p>

<p style="font-size:12px;color:#666;">If the button does not work, copy and paste this URL into your browser:<br>{{ $resetUrl }}</p>

<p style="font-size:12px;color:#666;">If you did not request a password reset, you can safely ignore this email — your password will not change.</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
