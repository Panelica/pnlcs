<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<p>Hello {{ $recipientName }},</p>
<div>{!! nl2br(e($body)) !!}</div>
</body>
</html>
