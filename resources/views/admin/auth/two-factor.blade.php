<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $textDirection ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('admin.auth.two_factor_title') }} - {{ company_name() }}</title>
    @vite(["resources/css/app.css"])
    @include('admin.auth.partials.head')
</head>
<body style="margin:0;padding:0;background:#f6f6f6;font-family:Inter,-apple-system,BlinkMacSystemFont,sans-serif;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;">

<div style="width:100%;max-width:400px;padding:20px;">
    <div style="text-align:center;margin-bottom:24px;">
        @include('admin.auth.partials.mark')
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

            <div style="margin-bottom:16px;padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;">
                <label style="display:flex;align-items:flex-start;gap:8px;font-size:13px;font-weight:500;cursor:pointer;">
                    <input type="checkbox" name="trust_device" value="1" @checked(old('trust_device')) style="margin-top:2px;">
                    <span>{{ __('admin.auth.trust_device') }}</span>
                </label>
                <div style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:13px;color:#555;">
                    <label for="trust_days">{{ __('admin.auth.trust_days') }}</label>
                    <input id="trust_days" type="number" name="trust_days" min="1" max="365" value="{{ old('trust_days', 30) }}"
                           style="width:76px;padding:6px 8px;border:1px solid #ddd;border-radius:5px;font-size:13px;">
                    <span>{{ __('admin.auth.trust_days_unit') }}</span>
                </div>
                <p style="margin:8px 0 0;font-size:12px;color:#888;line-height:1.45;">{{ __('admin.auth.trust_device_hint') }}</p>
            </div>

            <button type="submit" style="width:100%;padding:12px;background:var(--theme-primary,#1a4d80);color:#fff;border:none;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;">{{ __('admin.auth.verify') }}</button>
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
