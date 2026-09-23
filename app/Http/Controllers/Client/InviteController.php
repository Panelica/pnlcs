<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Accept an invitation to a customer account (sent by the API's
 * createclientinvite).
 *
 * The invitation is for one address. Somebody without a login makes one here;
 * somebody who already has one signs in with it first and accepts - a link
 * alone never attaches an existing login to an account, or anyone holding the
 * email could hand it access.
 */
class InviteController extends Controller
{
    public function show(string $token)
    {
        $invite = UserInvite::findByPlainToken($token);
        if (! $invite || ! $invite->isOpen()) {
            return redirect()->route('client.login')->withErrors(['email' => __('client.invite.invalid')]);
        }

        return view('client.auth.invite', [
            'token' => $token,
            'invite' => $invite,
            'account' => $invite->client,
            'hasLogin' => User::where('email', $invite->email)->exists(),
            'signedInAs' => auth()->user(),
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $invite = UserInvite::findByPlainToken($token);
        if (! $invite || ! $invite->isOpen()) {
            return redirect()->route('client.login')->withErrors(['email' => __('client.invite.invalid')]);
        }

        $key = 'invite-accept:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withErrors(['email' => __('client.invite.too_many')]);
        }
        RateLimiter::hit($key, 60);

        $existing = User::where('email', $invite->email)->first();

        if ($existing) {
            // Only the owner of that login, signed in, may accept for it.
            if (! auth()->check() || auth()->id() !== $existing->id) {
                return redirect()->route('client.login')->withErrors(['email' => __('client.invite.sign_in_first', ['email' => $invite->email])]);
            }
            $user = $existing;
        } else {
            $validated = $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'password' => 'required|string|min:8|confirmed',
            ]);
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $invite->email,
                'password' => Hash::make($validated['password']),
            ]);
            // The address was proven by the invitation email itself.
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->clients()->syncWithoutDetaching([$invite->client_id => [
            'owner' => false,
            'permissions' => json_encode($invite->permissions ?? \App\Support\ClientPermissions::ALL),
        ]]);
        $invite->update(['accepted_at' => now()]);

        ActivityLog::log('Invitation accepted by '.$invite->email, $invite->email, $invite->client_id);

        if (auth()->id() !== $user->id) {
            auth()->login($user);
            $request->session()->regenerate();
        }
        session(['active_client_id' => $invite->client_id]);

        return redirect()->route('client.home')->with('success', __('client.invite.accepted'));
    }
}
