<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $key = Str::transliterate(Str::lower($request->input('username')) . '|' . $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'username' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        // Accept either the username or the email in the same field, so an
        // administrator can sign in with whichever they remember.
        $loginField = filter_var($credentials['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $attemptCredentials = [$loginField => $credentials['username'], 'password' => $credentials['password']];
        if (Auth::guard('admin')->attempt($attemptCredentials, $request->boolean('remember'))) {
            RateLimiter::clear($key);
            $admin = Auth::guard('admin')->user();

            if ($admin->is_disabled) {
                Auth::guard('admin')->logout();
                return back()->withErrors(['username' => __('auth.disabled')]);
            }

            $admin->update([
                'last_login' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            $request->session()->regenerate();

            // Check 2FA
            if ($admin->second_factor_type && $admin->second_factor_secret) {
                // Start from unverified: regenerate() above carries the session
                // DATA across a fresh login, so a leftover admin_2fa_verified
                // from a re-login over a live session would wave this admin
                // through without their own second factor. Ordinary logout
                // invalidates the session; a re-login does not.
                session()->forget('admin_2fa_verified');
                session(['admin_2fa_pending' => true]);
                return redirect()->route('admin.2fa.verify');
            }

            return redirect()->intended(route('admin.dashboard'));
        }

        RateLimiter::hit($key, 900);
        return back()->withErrors(['username' => __('auth.failed')])->onlyInput('username');
    }

    public function show2faVerify()
    {
        if (!Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        return view('admin.auth.two-factor');
    }

    public function verify2fa(Request $request, TwoFactorService $twoFactor)
    {
        $request->validate(['code' => 'required|string']);

        $admin = Auth::guard('admin')->user();
        if (!$admin) {
            return redirect()->route('admin.login');
        }

        $code = $request->input('code');

        // Try TOTP code first
        if ($twoFactor->verify($admin->second_factor_secret, $code)) {
            session(['admin_2fa_verified' => true]);
            session()->forget('admin_2fa_pending');
            return redirect()->intended(route('admin.dashboard'));
        }

        // Try backup code
        $backupCodes = $admin->backup_codes ?? [];
        if (!empty($backupCodes)) {
            $result = $twoFactor->verifyBackupCode($backupCodes, $code);
            if ($result['valid']) {
                $admin->update(['backup_codes' => $result['remaining']]);
                session(['admin_2fa_verified' => true]);
                session()->forget('admin_2fa_pending');
                return redirect()->intended(route('admin.dashboard'));
            }
        }

        return back()->withErrors(['code' => __('auth.invalid_verification_code')]);
    }

    public function enable2fa(Request $request, TwoFactorService $twoFactor)
    {
        $admin = Auth::guard('admin')->user();

        if ($request->isMethod('get')) {
            $secret = session('2fa_setup_secret', $twoFactor->generateSecret());
            session(['2fa_setup_secret' => $secret]);

            $qrUrl = $twoFactor->getQrCodeUrl($admin->email, $secret);

            return view('admin.auth.enable-two-factor', [
                'secret' => $secret,
                'qrUrl' => $qrUrl,
            ]);
        }

        $request->validate(['code' => 'required|string|size:6']);

        $secret = session('2fa_setup_secret');
        if (!$secret || !$twoFactor->verify($secret, $request->code)) {
            return back()->withErrors(['code' => __('auth.invalid_code_try_again')]);
        }

        $backupCodes = $twoFactor->generateBackupCodes();

        $admin->update([
            'second_factor_type' => 'totp',
            'second_factor_secret' => $secret,
        ]);

        // Store backup codes on user model (admin doesn't have backup_codes field, store in notes or add)
        session()->forget('2fa_setup_secret');
        session(['admin_2fa_verified' => true]);

        return redirect()->route('admin.my-account')->with('success', __('admin.messages.2fa_enabled', ['codes' => implode(', ', $backupCodes)]));
    }

    public function disable2fa(Request $request)
    {
        $request->validate(['password' => 'required|string']);

        $admin = Auth::guard('admin')->user();

        if (!Auth::guard('admin')->validate(['username' => $admin->username, 'password' => $request->password])) {
            return back()->withErrors(['password' => __('auth.incorrect_password')]);
        }

        $admin->update([
            'second_factor_type' => null,
            'second_factor_secret' => null,
        ]);

        session()->forget('admin_2fa_verified');

        return back()->with('success', __('messages.success.2fa_has_been_disabled'));
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
