<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Events\ClientCreated;

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

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Check 2FA
            if ($user->second_factor_type && $user->second_factor_secret) {
                session(['2fa_pending' => true]);
                return redirect()->route('client.2fa.verify');
            }

            return redirect()->intended(route('client.home'));
        }

        return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
    }

    public function show2faVerify()
    {
        if (!Auth::check()) {
            return redirect()->route('client.login');
        }

        return view('client.auth.two-factor');
    }

    public function verify2fa(Request $request, TwoFactorService $twoFactor)
    {
        $request->validate(['code' => 'required|string']);

        $user = Auth::user();
        if (!$user) {
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
        if (!empty($backupCodes)) {
            $result = $twoFactor->verifyBackupCode($backupCodes, $code);
            if ($result['valid']) {
                $user->update(['backup_codes' => $result['remaining']]);
                session(['2fa_verified' => true]);
                session()->forget('2fa_pending');
                return redirect()->intended(route('client.home'));
            }
        }

        return back()->withErrors(['code' => 'Invalid verification code.']);
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
        if (!$secret || !$twoFactor->verify($secret, $request->code)) {
            return back()->withErrors(['code' => 'Invalid code. Please try again.']);
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

        if (!Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Incorrect password.']);
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
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'company_name' => 'nullable|string|max:255',
            'address1' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:2',
            'phone_number' => 'nullable|string|max:30',
            'tos' => 'required|accepted',
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $client = Client::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'company_name' => $validated['company_name'] ?? null,
            'address1' => $validated['address1'] ?? null,
            'city' => $validated['city'] ?? null,
            'country' => $validated['country'] ?? 'US',
            'phone_number' => $validated['phone_number'] ?? null,
        ]);
        $client->users()->attach($user->id, ['owner' => true]);

        event(new ClientCreated($client));

        Auth::login($user);
        return redirect()->route('client.home');
    }

    public function logout(Request $request)
    {
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
        if (!$user) {
            return back()->with('success', __('messages.success.if_an_account_exists_with_that_email_a_password_re'));
        }
        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );
        Log::info("Password reset for {$request->email}: {$token}");
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
        if (!$record || !Hash::check($request->token, $record->token)) {
            return back()->withErrors(['email' => 'Invalid or expired reset token.']);
        }
        if (now()->diffInMinutes($record->created_at) > 60) {
            return back()->withErrors(['email' => 'Reset link expired. Request a new one.']);
        }
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return back()->withErrors(['email' => 'User not found.']);
        }
        $user->password = Hash::make($request->password);
        $user->save();
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        return redirect()->route('client.login')->with('success', __('messages.success.password_reset_successfully_please_log_in'));
    }
}
