<!DOCTYPE html>
<html lang="en" data-theme="{{ request()->cookie('pnlcs_theme') === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('client.auth.2fa_title') }} - {{ company_name() }}</title>
    @vite(['resources/css/app.css'])
    <style>
        * { box-sizing: border-box; }
        body { margin:0; padding:0; font-family:Inter,-apple-system,sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; background:var(--bg, #f4f6f9); }
        .card { width:100%; max-width:400px; background:var(--card-bg, #fff); border-radius:12px; padding:32px; box-shadow:0 2px 12px rgba(0,0,0,.08); }
        .card h2 { text-align:center; font-size:20px; margin:0 0 4px; color:var(--heading, #1e293b); }
        .card p.sub { text-align:center; color:var(--muted, #888); font-size:14px; margin:0 0 24px; }
        label { display:block; font-size:13px; font-weight:500; margin-bottom:6px; color:var(--label, #555); }
        input[type=text] { width:100%; padding:12px; border:1px solid var(--border, #ddd); border-radius:8px; font-size:22px; text-align:center; letter-spacing:6px; }
        .btn { width:100%; padding:12px; background:var(--primary, #405189); color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; margin-top:12px; }
        .btn:hover { opacity:.9; }
        .alert { background:#fee; border:1px solid #fcc; color:#c00; padding:10px; border-radius:6px; margin-bottom:16px; font-size:13px; }
    </style>
</head>
<body>
<div class="card">
    <h2>{{ __('client.auth.2fa_heading') }}</h2>
    <p class="sub">{{ __('client.auth.2fa_subtitle') }}</p>

    @if($errors->any())
    <div class="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('client.2fa.verify.submit') }}">
        @csrf
        <div style="margin-bottom:16px;">
            <label>{{ __('client.auth.verification_code') }}</label>
            <input type="text" name="code" autofocus autocomplete="one-time-code" inputmode="numeric" maxlength="9" placeholder="000000">
        </div>
        <button type="submit" class="btn">{{ __('client.auth.verify') }}</button>
    </form>

    <p style="text-align:center;margin-top:14px;font-size:12px;color:var(--muted,#888);">{{ __('client.auth.backup_code_hint') }}</p>

    <form method="POST" action="{{ route('client.logout') }}" style="text-align:center;margin-top:8px;">
        @csrf
        <button type="submit" style="background:none;border:none;color:var(--muted,#888);cursor:pointer;font-size:13px;">{{ __('client.auth.cancel_logout') }}</button>
    </form>
</div>
</body>
</html>
