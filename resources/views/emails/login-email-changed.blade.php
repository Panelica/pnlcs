<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

<p>The address used to sign in to your account has been changed from {{ $previousEmail }} to {{ $newEmail }}.</p>

<p>The change was made from a signed-in session and confirmed with the account password.</p>

<p style="font-size:13px;color:#666;">If this was not you, contact us straight away — whoever made the change can now receive password reset links for the account.</p>

<p style="color:#888;font-size:12px;margin-top:30px;">{{ $companyName }}</p>
</body></html>
