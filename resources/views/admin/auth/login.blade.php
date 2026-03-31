<!DOCTYPE html>
<html lang="{{ str_replace("_", "-", app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - PNLCS</title>
    @vite(["resources/css/app.css"])
</head>
<body style="margin:0;padding:0;background:#f6f6f6;font-family:Inter,-apple-system,BlinkMacSystemFont,sans-serif;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;">

<div style="width:100%;max-width:400px;padding:20px;">
    <!-- Logo / Header -->
    <div style="text-align:center;margin-bottom:24px;">
        <div style="display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;background:#1a4d80;border-radius:8px;margin-bottom:12px;">
            <span style="color:#fff;font-size:22px;font-weight:700;">P</span>
        </div>
        <h1 style="margin:0;font-size:22px;font-weight:700;color:#1a4d80;">PNLCS</h1>
        <p style="margin:4px 0 0;font-size:13px;color:#666;">Administration Area</p>
    </div>

    <!-- Card -->
    <div style="background:#fff;border:1px solid #ddd;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,0.08);padding:28px 32px;">

        @if ($errors->any())
        <div style="background:#f2dede;border:1px solid #ebccd1;color:#a94442;padding:10px 14px;border-radius:4px;font-size:13px;margin-bottom:18px;">
            {{ $errors->first() }}
        </div>
        @endif

        @if (session("status"))
        <div style="background:#dff0d8;border:1px solid #d6e9c6;color:#3c763d;padding:10px 14px;border-radius:4px;font-size:13px;margin-bottom:18px;">
            {{ session("status") }}
        </div>
        @endif

        <form method="POST" action="{{ route("admin.login.submit") }}">
            @csrf

            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" id="username" value="{{ old("username") }}"
                    autocomplete="username" required
                    class="form-control"
                    placeholder="Enter your username">
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password"
                    autocomplete="current-password" required
                    class="form-control"
                    placeholder="••••••••">
            </div>

            <div style="margin-bottom:18px;display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="remember" id="remember"
                    style="width:14px;height:14px;accent-color:#337ab7;cursor:pointer;">
                <label for="remember" style="font-size:13px;color:#555;cursor:pointer;margin:0;">Remember me</label>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:8px 14px;font-size:14px;">
                Sign In
            </button>
        </form>
    </div>

    <!-- Footer -->
    <p style="text-align:center;font-size:12px;color:#999;margin-top:20px;">
        &copy; {{ date("Y") }} PNLCS. All rights reserved.
    </p>
</div>

</body>
</html>
