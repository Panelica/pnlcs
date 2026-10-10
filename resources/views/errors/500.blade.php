@php
    // The site's name; a 500 can be the database itself, so never let it fail.
    try { $errorSite = company_name(); } catch (\Throwable) { $errorSite = config('app.name'); }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $textDirection ?? 'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ __('errors.500.title') }} - {{ $errorSite }}</title>
<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',-apple-system,sans-serif;background:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;color:#1e293b}.c{text-align:center;padding:40px}.code{font-size:120px;font-weight:900;color:#ef4444;line-height:1;letter-spacing:-4px}h1{font-size:24px;font-weight:700;margin:16px 0 8px}p{color:#64748b;font-size:15px;margin-bottom:24px}a{display:inline-flex;padding:10px 24px;background:#1a4d80;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;transition:background 0.2s}a:hover{background:#143d66}</style></head>
<body><div class="c"><div class="code">500</div><h1>{{ __('errors.500.title') }}</h1><p>{{ __('errors.500.message') }}</p><a href="{{ url('/') }}">{{ __('errors.go_home') }}</a></div></body></html>
