<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ __('client.marketing.unsubscribe_title') }} - {{ company_name() }}</title>
    <style>
        body { margin:0; font-family:Inter,-apple-system,sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; background:var(--bg, #f4f6f9); color:var(--text, #1e293b); }
        .card { width:100%; max-width:420px; background:var(--card, #fff); border-radius:12px; padding:32px; box-shadow:0 2px 12px rgba(0,0,0,.08); text-align:center; margin:16px; }
        h1 { font-size:20px; margin:0 0 10px; }
        p { color:var(--muted, #666); font-size:14px; line-height:1.5; }
        button { padding:12px 20px; background:#405189; color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; }
    </style>
    @include('partials.locale-alternates')
</head>
<body>
<div class="card">
    <h1>{{ company_name() }}</h1>
    @if($done)
    <p>{{ __('client.marketing.unsubscribed', ['email' => $email]) }}</p>
    @else
    <p>{{ __('client.marketing.unsubscribe_ask', ['email' => $email]) }}</p>
    <form method="POST" action="{{ $action }}">
        @csrf
        <button type="submit">{{ __('client.marketing.unsubscribe_button') }}</button>
    </form>
    @endif
    <p style="font-size:12px;">{{ __('client.marketing.service_still') }}</p>
</div>
</body>
</html>
