@php($company = $companyName ?? config('app.name'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $textDirection ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('client.maintenance.title') }} — {{ $company }}</title>
    <style>
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
               font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
               background:#f5f6f8; color:#2c3345; }
        .box { max-width:520px; padding:40px; text-align:center; background:#fff;
               border:1px solid #e5e7eb; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.06); }
        h1 { margin:0 0 12px; font-size:22px; }
        p { margin:0; font-size:14px; line-height:1.6; color:#5b6478; }
        .brand { margin-bottom:18px; font-size:13px; letter-spacing:.08em; text-transform:uppercase; color:#98a1b3; }
    </style>
</head>
<body>
    <div class="box">
        <div class="brand">{{ $company }}</div>
        <h1>{{ __('client.maintenance.title') }}</h1>
        <p>{{ __('client.maintenance.body') }}</p>
    </div>
</body>
</html>
