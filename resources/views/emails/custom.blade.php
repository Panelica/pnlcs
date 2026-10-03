<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
<h2 style="color:#405189;">{{ $companyName }}</h2>

@foreach(preg_split('/\n{2,}/', trim($body)) as $paragraph)
<p style="line-height:1.6;">{!! nl2br(e($paragraph), false) !!}</p>
@endforeach
</body></html>
