<?php

namespace App\Http\Controllers\Client;

use App\Enums\ClientStatus;
use App\Events\ClientCreated;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AffiliateTracking;
use App\Mail\PasswordResetMail;
use App\Models\BannedEmail;
use App\Models\Client;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\TwoFactorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('client.home');
        }

        return view('client.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $key = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($key);
            $request->session()->regenerate();

            $user = Auth::user();

            // A closed account is finished. Nothing read the status the admin
            // screen sets, so a customer whose account had been closed could
            // still sign in and carry on.
            if ($user->clients()->exists() && ! $user->clients()->where('status', '!=', ClientStatus::Closed->value)->exists()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors(['email' => __('auth.account_closed')])->onlyInput('email');
            }

            // Whatever the visitor put in the cart before logging in changes
            // hands here. The cart id lives in the session DATA, which
            // regenerate() carries across - keyed by the session id it used
            // to evaporate at this exact line.
            $guestCartId = session('guest_cart_id');
            if ($guestCartId) {
                $client = $user->clients()->first();
                if ($client) {
                    \App\Models\Cart::whereKey($guestCartId)->whereNull('user_id')
                        ->update(['user_id' => $client->id]);
                    session()->forget('guest_cart_id');
                }
            }

            $user->forceFill([
                'last_login' => now(),
                'last_login_ip' => $request->ip(),
            ])->save();

            // Check 2FA
            if ($user->second_factor_type && $user->second_factor_secret) {
                // Start from unverified every time: 2fa_verified is a plain
                // session key that regenerate() carries across a fresh login,
                // and impersonation sets it deliberately. A stale one here
                // would wave a real 2FA login straight through.
                session()->forget('2fa_verified');
                session(['2fa_pending' => true]);

                return redirect()->route('client.2fa.verify');
            }

            return redirect()->intended(route('client.home'));
        }

        RateLimiter::hit($key, 900);

        return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
    }

    public function show2faVerify()
    {
        if (! Auth::check()) {
            return redirect()->route('client.login');
        }

        return view('client.auth.two-factor');
    }

    public function verify2fa(Request $request, TwoFactorService $twoFactor)
    {
        $request->validate(['code' => 'required|string']);

        $user = Auth::user();
        if (! $user) {
            return redirect()->route('client.login');
        }

        $code = $request->input('code');

        // Try TOTP code
        if ($twoFactor->verify($user->second_factor_secret, $code)) {
            session(['2fa_verified' => true]);
            session()->forget('2fa_pending');

            return redirect()->intended(route('client.home'));
        }

        // Try backup code
        $backupCodes = $user->backup_codes ?? [];
        if (! empty($backupCodes)) {
            $result = $twoFactor->verifyBackupCode($backupCodes, $code);
            if ($result['valid']) {
                $user->update(['backup_codes' => $result['remaining']]);
                session(['2fa_verified' => true]);
                session()->forget('2fa_pending');

                return redirect()->intended(route('client.home'));
            }
        }

        return back()->withErrors(['code' => __('auth.invalid_verification_code')]);
    }

    public function enable2fa(Request $request, TwoFactorService $twoFactor)
    {
        $user = Auth::user();

        if ($request->isMethod('get')) {
            $secret = session('2fa_setup_secret', $twoFactor->generateSecret());
            session(['2fa_setup_secret' => $secret]);

            $qrUrl = $twoFactor->getQrCodeUrl($user->email, $secret);

            return view('client.auth.enable-two-factor', [
                'secret' => $secret,
                'qrUrl' => $qrUrl,
            ]);
        }

        $request->validate(['code' => 'required|string|size:6']);

        $secret = session('2fa_setup_secret');
        if (! $secret || ! $twoFactor->verify($secret, $request->code)) {
            return back()->withErrors(['code' => __('auth.invalid_code_try_again')]);
        }

        $backupCodes = $twoFactor->generateBackupCodes();

        $user->update([
            'second_factor_type' => 'totp',
            'second_factor_secret' => $secret,
            'backup_codes' => $backupCodes,
        ]);

        session()->forget('2fa_setup_secret');
        session(['2fa_verified' => true]);

        return redirect()->route('client.account.security')->with('success', __('messages.success.2fa_enabled_successfully'))->with('backup_codes', $backupCodes);
    }

    public function disable2fa(Request $request)
    {
        $request->validate(['password' => 'required|string']);

        $user = Auth::user();

        if (! Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => __('auth.incorrect_password')]);
        }

        $user->update([
            'second_factor_type' => null,
            'second_factor_secret' => null,
            'backup_codes' => null,
        ]);

        session()->forget('2fa_verified');

        return back()->with('success', __('messages.success.2fa_has_been_disabled'));
    }

    public function showRegister()
    {
        return view('client.auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|unique:clients,email',
            'password' => 'required|string|min:8|confirmed',
            'company_name' => 'nullable|string|max:255',
            'address1' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:2',
            'phone_number' => 'nullable|string|max:30',
            'tos' => 'required|accepted',
        ]);

        // The ban list was only ever consulted when scoring an order, so a
        // banned address could open an account and be back inside the panel.
        if (BannedEmail::blocks($validated['email'])) {
            return back()
                ->withErrors(['email' => __('auth.email_not_accepted')])
                ->onlyInput('email');
        }

        // Checkout opens accounts too now; one implementation for both doors.
        [$user] = app(\App\Services\ClientRegistrationService::class)->register($validated, $request);

        Auth::login($user);

        return redirect()->route('client.home');
    }

    public function logout(Request $request)
    {
        session()->forget('active_client_id');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('client.login');
    }

    public function showForgotPassword()
    {
        return view('client.auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();
        if (! $user) {
            return back()->with('success', __('messages.success.if_an_account_exists_with_that_email_a_password_re'));
        }
        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );
        $resetUrl = route('client.password.reset', ['token' => $token]).'?email='.urlencode($request->email);
        try {
            Mail::to($request->email)->send(new PasswordResetMail($resetUrl, $request->email));
        } catch (\Throwable $e) {
            // Never log the token; only the delivery failure.
            Log::error('Password reset email failed for '.$request->email.': '.$e->getMessage());
        }

        return back()->with('success', __('messages.success.if_an_account_exists_with_that_email_a_password_re'));
    }

    public function showResetForm(Request $request, string $token)
    {
        return view('client.auth.reset-password', ['token' => $token, 'email' => $request->email]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);
        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();
        if (! $record || ! Hash::check($request->token, $record->token)) {
            return back()->withErrors(['email' => __('auth.invalid_or_expired_reset_token')]);
        }
        // diffInMinutes() is signed: for a time in the past it returns a
        // negative number, so the old '> 60' was never true and every link
        // stayed valid for good. Compare against the deadline instead.
        if (Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            return back()->withErrors(['email' => __('auth.reset_link_expired')]);
        }
        $user = User::where('email', $request->email)->first();
        if (! $user) {
            return back()->withErrors(['email' => __('auth.user_not_found')]);
        }
        $user->password = Hash::make($request->password);

        // Resetting a password is what somebody does when they think another
        // person is in their account. Any "remember me" cookie already handed
        // out has to stop working, or the reset changes nothing for them.
        $user->setRememberToken(Str::random(60));
        $user->save();
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return redirect()->route('client.login')->with('success', __('messages.success.password_reset_successfully_please_log_in'));
    }
}
