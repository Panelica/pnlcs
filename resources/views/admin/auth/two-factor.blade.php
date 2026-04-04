<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - PNLCS Admin</title>
    @vite(["resources/css/app.css"])
</head>
<body style="margin:0;padding:0;background:#f6f6f6;font-family:Inter,-apple-system,BlinkMacSystemFont,sans-serif;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;">

<div style="width:100%;max-width:400px;padding:20px;">
    <div style="text-align:center;margin-bottom:24px;">
        <div style="display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;background:#1a4d80;border-radius:8px;margin-bottom:12px;">
            <span style="color:#fff;font-size:22px;font-weight:700;">P</span>
        </div>
        <h2 style="margin:0;font-size:20px;font-weight:600;">Two-Factor Authentication</h2>
        <p style="margin:6px 0 0;color:#888;font-size:14px;">Enter the 6-digit code from your authenticator app</p>
    </div>

    <div style="background:#fff;border-radius:10px;padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.06);">
        @if($errors->any())
        <div style="background:#fee;border:1px solid #fcc;color:#c00;padding:10px;border-radius:6px;margin-bottom:16px;font-size:13px;">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.2fa.verify.submit') }}">
            @csrf
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:13px;font-weight:500;margin-bottom:6px;">Verification Code</label>
                <input type="text" name="code" autofocus autocomplete="one-time-code" inputmode="numeric" maxlength="9"
                       style="width:100%;padding:12px;border:1px solid #ddd;border-radius:6px;font-size:22px;text-align:center;letter-spacing:6px;box-sizing:border-box;" placeholder="000000">
            </div>
            <button type="submit" style="width:100%;padding:12px;background:#1a4d80;color:#fff;border:none;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;">Verify</button>
        </form>

        <p style="text-align:center;margin-top:14px;font-size:12px;color:#888;">You can also enter a backup code</p>
    </div>

    <form method="POST" action="{{ route('admin.logout') }}" style="text-align:center;margin-top:16px;">
        @csrf
        <button type="submit" style="background:none;border:none;color:#888;cursor:pointer;font-size:13px;">Cancel &amp; Logout</button>
    </form>
</div>

</body>
</html>
