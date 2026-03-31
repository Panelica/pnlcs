<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Login - PNLCS</title>
    @vite(['resources/css/app.css'])
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; font-size: 13px; background: #f6f6f6; color: #333; margin: 0; min-height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 20px; }
        .login-box { width: 100%; max-width: 400px; }
        .login-logo { text-align: center; margin-bottom: 24px; }
        .login-logo h1 { font-size: 26px; font-weight: 700; color: #1a4d80; margin: 0 0 6px; }
        .login-logo p { font-size: 13px; color: #777; margin: 0; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .card-body { padding: 28px; }
        .form-group { margin-bottom: 14px; }
        .form-label { display: block; font-size: 13px; font-weight: 500; color: #555; margin-bottom: 4px; }
        .form-control { display: block; width: 100%; padding: 7px 12px; font-size: 13px; color: #555; background: #fff; border: 1px solid #ccc; border-radius: 4px; transition: border-color 0.15s; }
        .form-control:focus { border-color: #66afe9; outline: 0; box-shadow: 0 0 6px rgba(102,175,233,.5); }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 7px 16px; border-radius: 4px; font-size: 13px; font-weight: 500; cursor: pointer; border: 1px solid transparent; text-decoration: none; width: 100%; }
        .btn-primary { background: #337ab7; color: #fff; border-color: #2e6da4; }
        .btn-primary:hover { background: #286090; }
        .alert { padding: 9px 12px; border-radius: 4px; font-size: 13px; margin-bottom: 14px; background: #f2dede; border: 1px solid #ebccd1; color: #a94442; }
        .remember-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .remember-row label { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #555; cursor: pointer; }
        .remember-row a { font-size: 13px; color: #337ab7; text-decoration: none; }
        .register-link { text-align: center; margin-top: 16px; font-size: 13px; color: #777; }
        .register-link a { color: #337ab7; font-weight: 500; text-decoration: none; }
    </style>
</head>
<body>
<div class="login-box">
    <div class="login-logo">
        <h1>PNLCS</h1>
        <p>Client Area</p>
    </div>
    <div class="card">
        <div class="card-body">
            @if($errors->any())
                <div class="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('client.login.submit') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus class="form-control" placeholder="you@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" required class="form-control" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;">
                </div>
                <div class="remember-row">
                    <label>
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <a href="#">Forgot Password?</a>
                </div>
                <button type="submit" class="btn btn-primary">Sign In</button>
            </form>
        </div>
    </div>
    <div class="register-link">
        Don&rsquo;t have an account? <a href="{{ route('client.register') }}">Register</a>
    </div>
</div>
</body>
</html>
