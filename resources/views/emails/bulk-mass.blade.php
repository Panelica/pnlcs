<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<p>Hello {{ $recipientName }},</p>
<div>{!! nl2br(e($body)) !!}</div>
@if(! empty($unsubscribeUrl))
<p style="color:#888;font-size:12px;margin-top:30px;">{{ __('client.marketing.mail_foot') }} <a href="{{ $unsubscribeUrl }}" style="color:#888;">{{ __('client.marketing.unsubscribe') }}</a></p>
@endif
</body>
</html>
