<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminTrustedDevice;
use App\Services\TwoFactorService;
use App\Support\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
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
                session()->forget(['admin_2fa_verified', 'admin_2fa_pending']);

                $trusted = AdminTrustedDevice::findValid(
                    $admin,
                    $request->cookie(AdminTrustedDevice::COOKIE_NAME)
                );

                if ($trusted) {
                    $trusted->markUsed();
                    session(['admin_2fa_verified' => true]);

                    return redirect()->intended(route('admin.dashboard'));
                }

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
        $validated = $request->validate([
            'code' => 'required|string',
            'trust_device' => 'nullable|boolean',
            'trust_days' => 'exclude_unless:trust_device,1|nullable|integer|min:1|max:'.AdminTrustedDevice::MAX_DAYS,
        ]);

        $admin = Auth::guard('admin')->user();
        if (!$admin) {
            return redirect()->route('admin.login');
        }

        // Per account, whatever the address or the session: the route's own
        // limit is per visitor, and six digits do not survive a patient
        // guesser who changes either. Five wrong codes wait fifteen minutes.
        $attempts = 'second-factor:admin:'.$admin->id;
        if (RateLimiter::tooManyAttempts($attempts, 5)) {
            return back()->withErrors(['code' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($attempts)])]);
        }

        $code = $request->input('code');

        // Try TOTP code first
        if ($twoFactor->verify($admin->second_factor_secret, $code)) {
            $this->completeTwoFactor($request, $admin, $validated);
            RateLimiter::clear($attempts);
            return redirect()->intended(route('admin.dashboard'));
        }

        // Try backup code
        $backupCodes = $admin->backup_codes ?? [];
        if (!empty($backupCodes)) {
            $result = $twoFactor->verifyBackupCode($backupCodes, $code);
            if ($result['valid']) {
                $admin->update(['backup_codes' => $result['remaining']]);
                $this->completeTwoFactor($request, $admin, $validated);
                RateLimiter::clear($attempts);
                return redirect()->intended(route('admin.dashboard'));
            }
        }

        RateLimiter::hit($attempts, 15 * 60);

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
                // Drawn here: the address carries the secret and must not go to a QR service.
                'qrImage' => QrCode::dataUri($qrUrl),
            ]);
        }

        $request->validate(['code' => 'required|string|size:6']);

        $secret = session('2fa_setup_secret');
        if (!$secret || !$twoFactor->verify($secret, $request->code)) {
            return back()->withErrors(['code' => __('auth.invalid_code_try_again')]);
        }

        $backupCodes = $twoFactor->generateBackupCodes();

        // The recovery codes shown on the next page are the ones stored: they
        // used to be shown and thrown away, so none of them ever worked.
        $admin->update([
            'second_factor_type' => 'totp',
            'second_factor_secret' => $secret,
            'backup_codes' => $backupCodes,
        ]);

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
            'backup_codes' => null,
        ]);

        session()->forget('admin_2fa_verified');
        Cookie::expire(AdminTrustedDevice::COOKIE_NAME, '/');

        return back()->with('success', __('messages.success.2fa_has_been_disabled'));
    }

    private function completeTwoFactor(Request $request, Admin $admin, array $validated): void
    {
        session(['admin_2fa_verified' => true]);
        session()->forget('admin_2fa_pending');

        if (! $request->boolean('trust_device')) {
            return;
        }

        $days = (int) ($validated['trust_days'] ?? AdminTrustedDevice::DEFAULT_DAYS);
        $days = max(1, min(AdminTrustedDevice::MAX_DAYS, $days));

        $token = AdminTrustedDevice::issue(
            $admin,
            $days,
            $request->ip(),
            $request->userAgent()
        );

        Cookie::queue(
            AdminTrustedDevice::COOKIE_NAME,
            $token,
            $days * 24 * 60,
            '/',
            null,
            $request->isSecure() || (bool) config('session.secure'),
            true,
            false,
            'lax'
        );
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
