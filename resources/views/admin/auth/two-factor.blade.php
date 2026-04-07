<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('admin.auth.two_factor_title') }} - PNLCS</title>
    @vite(["resources/css/app.css"])
</head>
<body style="margin:0;padding:0;background:#f6f6f6;font-family:Inter,-apple-system,BlinkMacSystemFont,sans-serif;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;">

<div style="width:100%;max-width:400px;padding:20px;">
    <div style="text-align:center;margin-bottom:24px;">
        <div style="display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;background:#1a4d80;border-radius:8px;margin-bottom:12px;">
            <span style="color:#fff;font-size:22px;font-weight:700;">P</span>
        </div>
        <h2 style="margin:0;font-size:20px;font-weight:600;">{{ __('admin.auth.two_factor_title') }}</h2>
        <p style="margin:6px 0 0;color:#888;font-size:14px;">{{ __('admin.auth.two_factor_desc') }}</p>
    </div>

    <div style="background:#fff;border-radius:10px;padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.06);">
        @if($errors->any())
        <div style="background:#fee;border:1px solid #fcc;color:#c00;padding:10px;border-radius:6px;margin-bottom:16px;font-size:13px;">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.2fa.verify.submit') }}">
            @csrf
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:13px;font-weight:500;margin-bottom:6px;">{{ __('admin.auth.verification_code') }}</label>
                <input type="text" name="code" autofocus autocomplete="one-time-code" inputmode="numeric" maxlength="9"
                       style="width:100%;padding:12px;border:1px solid #ddd;border-radius:6px;font-size:22px;text-align:center;letter-spacing:6px;box-sizing:border-box;" placeholder="000000">
            </div>
            <button type="submit" style="width:100%;padding:12px;background:#1a4d80;color:#fff;border:none;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;">{{ __('admin.auth.verify') }}</button>
        </form>

        <p style="text-align:center;margin-top:14px;font-size:12px;color:#888;">{{ __('admin.auth.backup_code_hint') }}</p>
    </div>

    <form method="POST" action="{{ route('admin.logout') }}" style="text-align:center;margin-top:16px;">
        @csrf
        <button type="submit" style="background:none;border:none;color:#888;cursor:pointer;font-size:13px;">{{ __('admin.auth.cancel_logout') }}</button>
    </form>
</div>

</body>
</html>
